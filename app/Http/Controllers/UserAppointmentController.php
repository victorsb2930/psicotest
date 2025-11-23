<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\AppointmentCreditTransaction;
use App\Models\Appointment;
use Carbon\Carbon;

class UserAppointmentController extends Controller
{
    // show a calendar view for the authenticated user
    public function index()
    {
        return view('appointments.index');
    }

    // return events where the authenticated user is the patient
    public function events(Request $request)
    {
        $user = Auth::user();
        $events = Appointment::where('patient_id', $user->id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function($a){
                return [
                    'id' => $a->id,
                    'title' => $a->title ?: ($a->professional?->name ?? 'Cita'),
                    'start' => $a->start?->setTimezone('UTC')->toIso8601String(),
                    'end' => $a->end?->setTimezone('UTC')->toIso8601String(),
                    'allDay' => (bool) $a->all_day,
                    'status' => $a->status,
                    // extra props for UI modal
                    'notes' => $a->notes,
                    'rejection_reason' => $a->rejection_reason ?? null,
                    'professional_name' => $a->professional?->name,
                ];
            });
        return response()->json($events);
    }

    // Allow a patient to request a new appointment (simple flow)
    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'professional_id' => ['required','integer'],
            'appointment_type' => ['required','string','max:255'],
            'title' => ['required','string','max:255'],
            'start' => ['required','date'],
            'end' => ['required','date','after:start'],
            'notes' => ['required','string']
        ]);
        $start = Carbon::parse($data['start'])->setTimezone('UTC');
        $end = isset($data['end']) ? Carbon::parse($data['end'])->setTimezone('UTC') : null;

        // Validate against professional availability
        try {
            $svc = app(\App\Services\AvailabilityService::class);
            [$ok,$reason] = $svc->isSlotAvailable((int)$data['professional_id'], $start, $end ?? $start->copy()->addMinutes(30));
            if (!$ok) {
                return response()->json(['ok'=>false,'error'=>'availability','message'=>$reason ?? 'Horario no disponible'], 422);
            }
        } catch (\Throwable $ex) {
            \Log::error('Availability check failed for user appointment', ['err'=>$ex->getMessage()]);
        }

        // --- Quota / credits enforcement ---
        try {
            $userId = $user->id;
            // Find active subscription (if any) and read plan feature
            $activeSub = $user->subscriptions()->with('plan')->where(function($q){
                $q->where('status','active')
                  ->orWhereNull('ends_at')
                  ->orWhere('ends_at','>', now());
            })->orderBy('ends_at','desc')->first();

            $included = null;
            if ($activeSub && $activeSub->plan && is_array($activeSub->plan->features ?? null)) {
                $included = $activeSub->plan->features['appointments_included_per_month'] ?? null;
                if (is_string($included)) $included = (int)$included;
            }

            // Count used appointments this calendar month (created_at)
            $startMonth = Carbon::now()->startOfMonth();
            $endMonth = Carbon::now()->endOfMonth();
            $used = Appointment::where('patient_id', $userId)->whereBetween('created_at', [$startMonth, $endMonth])->count();

            // Purchased credits come from the persistent ledger now
            $purchased = (int) AppointmentCreditTransaction::getBalanceForUser($userId);

            $remainingIncluded = 0;
            if ($included !== null) {
                $remainingIncluded = max(0, (int)$included - (int)$used);
            }

            $totalAvailable = $remainingIncluded + $purchased;

            if ($totalAvailable <= 0) {
                return response()->json(['ok' => false, 'error' => 'quota_exceeded', 'message' => 'No tienes citas disponibles. Actualiza tu plan.'], 402);
            }

            // Decide whether we need to consume a purchased credit. We consume AFTER successfully creating the appointment
            $consumePurchased = false;
            if ($remainingIncluded <= 0 && $purchased > 0) {
                $consumePurchased = true;
            }
        } catch (\Throwable $e) {
            Log::error('Failed to evaluate appointment quotas for user '.$user->id, ['err' => $e->getMessage()]);
            // Fail closed: prevent creation if quota calculation fails unexpectedly
            return response()->json(['ok' => false, 'error' => 'quota_check_failed', 'message' => 'No se pudo verificar disponibilidad de créditos. Intenta más tarde.'], 500);
        }

        $appt = Appointment::create([
            'professional_id' => $data['professional_id'],
            'patient_id' => $user->id,
            'title' => $data['title'] ?? null,
            'start' => $start->toDateTimeString(),
            'end' => $end?->toDateTimeString() ?? null,
            'all_day' => false,
            'notes' => $data['notes'] ?? null,
            'appointment_type' => $data['appointment_type'] ?? null,
            'status' => 'pending'
        ]);

        try {
            \Log::info('appointment.requested', [
                'appointment_id' => $appt->id,
                'professional_id' => (int) $appt->professional_id,
                'patient_id' => (int) $appt->patient_id,
                'start' => (string) $appt->start,
                'end' => (string) ($appt->end ?? ''),
                'status' => $appt->status,
            ]);
        } catch (\Throwable $_) { }

        // If we decided to consume a purchased credit for this creation, decrement now (best-effort)
        try {
            if (!empty($consumePurchased)) {
                try {
                    AppointmentCreditTransaction::consumeCredits($user->id, 1, ['reason' => 'appointment_created', 'appointment_id' => $appt->id]);
                } catch (\Throwable $e) {
                    // Log but don't rollback appointment creation
                    Log::warning('Failed to consume appointment credit transaction for user '.$user->id, ['err' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $_) { /* swallow */ }

        // Notify the professional using the AppointmentCreated notification
        try {
            $appt->professional->notify(new \App\Notifications\AppointmentCreated($appt));
        } catch (\Throwable $e) {
            \Log::error('Failed to notify professional about new appointment', ['err' => $e->getMessage(), 'appointment_id' => $appt->id]);
            // Fallback: if professional has an email, try to send a simple mailable
            try {
                if (!empty($appt->professional?->email)) {
                    \Illuminate\Support\Facades\Mail::to($appt->professional->email)->send(new \App\Mail\ContactMessage([
                        'subject' => 'Nueva cita recibida',
                        'email' => config('mail.from.address'),
                        'name' => $appt->professional->name,
                        'message' => 'Has recibido una nueva solicitud de cita.',
                        'appointment' => $appt->toArray(),
                    ]));
                }
            } catch (\Throwable $_) {
                // swallow secondary failures but log
                \Log::error('Fallback mail send failed for appointment notification', ['appointment_id' => $appt->id]);
            }
        }

        return response()->json(['ok' => true, 'appointment' => $appt]);
    }
}

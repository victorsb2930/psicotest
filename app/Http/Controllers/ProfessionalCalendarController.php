<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ProfessionalCalendarController extends Controller
{
    public function index()
    {
        // view that loads calendar UI
        return view('professional.calendar');
    }

    public function events(Request $request)
    {
        $user = Auth::user();
        // return events where user is professional
        $events = Appointment::where('professional_id', $user->id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function($a){
                // Make sure we return UTC ISO strings so FullCalendar interprets times consistently.
                return [
                    'id' => $a->id,
                    'title' => $a->title ?: ($a->patient?->name ?? 'Cita'),
                    'start' => $a->start?->setTimezone('UTC')->toIso8601String(),
                    'end' => $a->end?->setTimezone('UTC')->toIso8601String(),
                    'allDay' => (bool) $a->all_day,
                    'status' => $a->status,
                    // include notes so frontend can show description in modals
                    'notes' => $a->notes ?? null,
                ];
            });
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'patient_id' => ['required','integer', Rule::exists('users','id')],
            'title' => ['nullable','string','max:255'],
            'start' => ['required','date'],
            // require end to be strictly after start when provided
            'end' => ['nullable','date','after:start'],
            'all_day' => ['sometimes','boolean'],
            'notes' => ['nullable','string'],
        ]);
        // Prevent creating appointments that start before today (no citas en fechas pasadas)
        // Parse incoming datetimes as UTC (client sends ISO strings in UTC)
        $start = Carbon::parse($data['start'])->setTimezone('UTC');
        $end = isset($data['end']) ? Carbon::parse($data['end'])->setTimezone('UTC') : null;
        $now = Carbon::now()->setTimezone('UTC');

        // Reject if start is before now (UTC-normalized)
        if ($start->lt($now)) {
            return response()->json([
                'error' => 'validation',
                'field' => 'start',
                'message' => 'La fecha/hora de inicio indicada es anterior al momento actual.'
            ], 422);
        }

        // If end was provided, ensure it's strictly after start (extra server-side guard)
        if ($end && !$end->gt($start)) {
            return response()->json([
                'error' => 'validation',
                'field' => 'end',
                'message' => 'La fecha/hora de fin debe ser posterior a la de inicio.'
            ], 422);
        }

        // Availability validation for professional creating appointment (respect own schedule)
        try {
            $svc = app(\App\Services\AvailabilityService::class);
            // if end not provided assume 30 min default slot
            $effectiveEnd = $end ?? $start->copy()->addMinutes(30);
            [$ok,$reason] = $svc->isSlotAvailable($user->id, $start, $effectiveEnd);
            if (!$ok) {
                return response()->json([
                    'error' => 'availability',
                    'field' => 'start',
                    'message' => $reason ?? 'Horario no disponible'
                ], 422);
            }
        } catch (\Throwable $ex) {
            \Log::error('Availability check failed (professional store)', ['err'=>$ex->getMessage()]);
        }

        // Check for overlapping appointments (treat touching intervals as conflict: existing.start <= new.end AND (existing.end IS NULL OR existing.end >= new.start))
        $conflicts = Appointment::where('professional_id', $user->id)
            ->whereNull('deleted_at')
            ->where(function($q) use ($start, $end) {
                $q->where('start', '<=', $end ?? $start)
                  ->where(function($q2) use ($start) {
                      $q2->whereNull('end')->orWhere('end', '>=', $start);
                  });
            })->get();

        if ($conflicts->isNotEmpty()) {
            return response()->json([
                'error' => 'conflict',
                'field' => 'start',
                'message' => 'La nueva cita solapa con citas existentes.',
                'conflicts' => $conflicts->map(function($c){
                    return [
                        'id' => $c->id,
                        'start' => $c->start?->setTimezone('UTC')->toIso8601String(),
                        'end' => $c->end?->setTimezone('UTC')->toIso8601String(),
                        'title' => $c->title,
                    ];
                })->values(),
            ], 422);
        }
        // ensure patient belongs to professional's patients? We'll assume any user can be chosen for now.
        $appoint = Appointment::create([
            'professional_id' => $user->id,
            'patient_id' => $data['patient_id'],
            'title' => $data['title'] ?? null,
            // Save normalized UTC datetimes
            'start' => $start->toDateTimeString(),
            'end' => $end?->toDateTimeString() ?? null,
            'all_day' => $data['all_day'] ?? false,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);
        // notify patient about the new appointment
        try {
            $appoint->patient->notify(new \App\Notifications\AppointmentCreated($appoint));
        } catch (\Throwable $ex) {
            // don't fail the request if notification sending fails; log in real app
        }
        return response()->json(['ok'=>true,'appointment'=>$appoint]);
    }

    // helper to search patients by name/email for selection in UI
    public function searchPatients(Request $request)
    {
        $q = trim((string) $request->get('q',''));
        $users = User::query()->where('name','like', "%{$q}%")->orWhere('email','like', "%{$q}%")->limit(20)->get(['id','name','email']);
        return response()->json($users);
    }
}

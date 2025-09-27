<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\SectionHistoryPsy;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function accept(Request $request, $appointment)
    {
        $user = Auth::user();
        // load appointment explicitly (avoid potential implicit binding oddities in tests)
        $appt = Appointment::withTrashed()->find($appointment);
        if (!$appt) {
            \Log::warning('Appointment accept: appointment not found', ['appointment_id' => $appointment, 'auth_user_id' => $user?->id]);
            return response()->json(['error' => 'No encontrado'], 404);
        }
        // extra debug information
        try {
            \Log::info('Appointment accept attempt', [
                'appointment_id' => $appt->id,
                'appointment_attributes' => $appt->toArray(),
                'db_row' => \Illuminate\Support\Facades\DB::table('appointments')->where('id', $appt->id)->first(),
                'auth_user_id' => $user?->id,
            ]);
        } catch (\Throwable $ex) {
            \Log::error('Error logging appointment debug data', ['err' => $ex->getMessage()]);
        }

        // Authorization: either the professional (owner) or the patient may act
        $isPatient = ((int)($appt->patient_id ?? 0) === (int)$user->id);
        $isProfessional = ((int)($appt->professional_id ?? 0) === (int)$user->id);
        if (!($isPatient || $isProfessional)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $appt->status = 'accepted';
        $appt->save();

        // Notify the other party: if patient accepted, notify professional; if professional accepted (creating directly), notify patient.
        try {
            if ($isPatient && $appt->professional) {
                $appt->professional->notify(new \App\Notifications\AppointmentAccepted($appt));
            } else if ($isProfessional && $appt->patient) {
                $appt->patient->notify(new \App\Notifications\AppointmentAccepted($appt));
            }
        } catch (\Throwable $ex) {}

        // Record acceptance in section_history_psy (best-effort)
        try {
            $startDt = $appt->start ? \Carbon\Carbon::parse($appt->start) : null;
            $endDt = $appt->end ? \Carbon\Carbon::parse($appt->end) : null;
            $duration = null;
            if ($startDt && $endDt) $duration = (int) $endDt->diffInMinutes($startDt);
            SectionHistoryPsy::create([
                'professional_id' => $appt->professional_id,
                'client_id' => $appt->patient_id,
                'session_datetime' => $startDt?->toDateTimeString() ?? null,
                'status' => 'accepted',
                'session_type' => $appt->appointment_type ?? null,
                'notes' => $appt->notes ?? null,
                'duration_minutes' => $duration,
            ]);
        } catch (\Throwable $_) {}

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, $appointment)
    {
        $user = Auth::user();
        $appt = Appointment::withTrashed()->find($appointment);
        if (!$appt) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        // Authorization: either the professional or the patient may reject
        $isPatient = ((int)($appt->patient_id ?? 0) === (int)$user->id);
        $isProfessional = ((int)($appt->professional_id ?? 0) === (int)$user->id);
        if (!($isPatient || $isProfessional)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $appt->status = 'rejected';
        $appt->save();
        $reason = $request->input('reason') ?? null;
        try {
            if ($isPatient && $appt->professional) {
                $appt->professional->notify(new \App\Notifications\AppointmentRejected($appt, $reason));
            } else if ($isProfessional && $appt->patient) {
                // notify the patient and include the professional-provided reason when available
                $appt->patient->notify(new \App\Notifications\AppointmentRejected($appt, $reason));
            }
        } catch (\Throwable $ex) {}

        // Record rejection in section_history_psy (best-effort)
        try {
            $startDt = $appt->start ? \Carbon\Carbon::parse($appt->start) : null;
            $reason = $request->input('reason') ?? null;
            SectionHistoryPsy::create([
                'professional_id' => $appt->professional_id,
                'client_id' => $appt->patient_id,
                'session_datetime' => $startDt?->toDateTimeString() ?? null,
                'status' => 'rejected',
                'session_type' => $appt->appointment_type ?? null,
                'notes' => $reason ?? $appt->notes ?? null,
                'duration_minutes' => null,
            ]);
        } catch (\Throwable $_) {}
        return response()->json(['ok' => true]);
    }
}

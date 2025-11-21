<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Models\AppointmentAudit;
use App\Events\AppointmentRescheduled;
use Illuminate\Http\Request;

class AppointmentRescheduleController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->id, [$appointment->professional_id, $appointment->patient_id], true)) abort(403);

        // Ensure reschedule allowed before deadline
        $deadlineHours = (int) config('appointments.reschedule_deadline_hours');
        if (now()->greaterThan($appointment->start->copy()->subHours($deadlineHours))) {
            return response()->json(['ok'=>false,'error'=>'deadline_passed'], 422);
        }

        $validated = $request->validate([
            'proposed_start' => ['required','date'],
            'proposed_end' => ['required','date','after:proposed_start'],
            'reason' => ['nullable','string','max:1000']
        ]);

        // Mark appointment status as reschedule_pending
        $prevStatus = $appointment->status;
        $appointment->status = 'reschedule_pending';
        $appointment->save();

        $res = AppointmentReschedule::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $user->id,
            'original_start' => $appointment->start,
            'original_end' => $appointment->end,
            'proposed_start' => $validated['proposed_start'],
            'proposed_end' => $validated['proposed_end'],
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
        ]);

        AppointmentAudit::record($appointment, 'reschedule_requested', $prevStatus, $appointment->status, [
            'reschedule_id' => $res->id,
            'proposed_start' => $res->proposed_start,
            'proposed_end' => $res->proposed_end,
        ]);
        event(new AppointmentRescheduled($appointment, $res));

        return response()->json(['ok'=>true,'reschedule'=>[ 'id'=>$res->id, 'status'=>$res->status ]]);
    }

    public function accept(Request $request, AppointmentReschedule $reschedule)
    {
        $user = auth()->user();
        if (!$user) abort(403);
        $appointment = $reschedule->appointment;
        if (!in_array($user->id, [$appointment->professional_id, $appointment->patient_id], true)) abort(403);
        if ($reschedule->status !== 'pending') return response()->json(['ok'=>false,'error'=>'not_pending'], 422);

        $reschedule->status = 'accepted';
        $reschedule->responded_at = now();
        $reschedule->save();

        // If both sides accepted OR single acceptance logic (simplified: one accept is enough)
        // Update appointment times
        $appointment->start = $reschedule->proposed_start;
        $appointment->end = $reschedule->proposed_end;
        $appointment->status = 'accepted';
        $appointment->save();

        AppointmentAudit::record($appointment, 'reschedule_accepted', 'reschedule_pending', $appointment->status, [
            'reschedule_id' => $reschedule->id,
        ]);
        event(new AppointmentRescheduled($appointment, $reschedule));

        return response()->json(['ok'=>true,'appointment'=>[ 'id'=>$appointment->id,'start'=>$appointment->start->toIso8601String(),'end'=>$appointment->end->toIso8601String(),'status'=>$appointment->status ]]);
    }

    public function reject(Request $request, AppointmentReschedule $reschedule)
    {
        $user = auth()->user();
        if (!$user) abort(403);
        $appointment = $reschedule->appointment;
        if (!in_array($user->id, [$appointment->professional_id, $appointment->patient_id], true)) abort(403);
        if ($reschedule->status !== 'pending') return response()->json(['ok'=>false,'error'=>'not_pending'], 422);

        $reschedule->status = 'rejected';
        $reschedule->responded_at = now();
        $reschedule->save();

        // Revert appointment status back to previous accepted/pending (if it was reschedule_pending)
        if ($appointment->status === 'reschedule_pending') {
            $appointment->status = 'accepted';
            $appointment->save();
        }

        AppointmentAudit::record($appointment, 'reschedule_rejected', 'reschedule_pending', $appointment->status, [
            'reschedule_id' => $reschedule->id,
        ]);
        event(new AppointmentRescheduled($appointment, $reschedule));

        return response()->json(['ok'=>true,'reschedule'=>[ 'id'=>$reschedule->id,'status'=>$reschedule->status ]]);
    }
}

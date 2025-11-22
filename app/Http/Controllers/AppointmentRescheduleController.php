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
        $this->authorize('requestReschedule', $appointment);
        $user = auth()->user();

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

        // Overlap validation: proposed window must not collide with other blocking appointments of either participant
        $proposedStart = now()->parse($validated['proposed_start']);
        $proposedEnd = now()->parse($validated['proposed_end']);
        $conflicts = Appointment::query()
            ->blocking()
            ->where(function($q) use ($appointment) {
                $q->where('professional_id', $appointment->professional_id)
                  ->orWhere('patient_id', $appointment->patient_id);
            })
            ->where('id', '!=', $appointment->id)
            ->where(function($q) use ($proposedStart, $proposedEnd) {
                // overlap: existing.start < proposedEnd AND existing.end > proposedStart
                $q->where('start', '<', $proposedEnd)
                  ->where('end', '>', $proposedStart);
            })
            ->exists();
        if($conflicts){
            return response()->json(['ok'=>false,'error'=>'overlap'], 422);
        }

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
        $user = auth()->user(); if(!$user) abort(403);
        $this->authorize('accept', $reschedule);
        $appointment = $reschedule->appointment;

        // Revalidate overlap before applying proposed times
        $proposedStart = $reschedule->proposed_start;
        $proposedEnd = $reschedule->proposed_end;
        $conflicts = Appointment::query()
            ->blocking()
            ->where(function($q) use ($appointment) {
                $q->where('professional_id', $appointment->professional_id)
                  ->orWhere('patient_id', $appointment->patient_id);
            })
            ->where('id', '!=', $appointment->id)
            ->where(function($q) use ($proposedStart, $proposedEnd) {
                $q->where('start', '<', $proposedEnd)
                  ->where('end', '>', $proposedStart);
            })
            ->exists();
        if($conflicts){
            return response()->json(['ok'=>false,'error'=>'overlap'], 422);
        }

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
        $user = auth()->user(); if(!$user) abort(403);
        $this->authorize('reject', $reschedule);
        $appointment = $reschedule->appointment;

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

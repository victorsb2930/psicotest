<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentAudit;
use Illuminate\Support\Facades\Log;

class PlanIntegrationService
{
    public function handlePostCompletion(Appointment $appointment): void
    {
        // Stub logic: mark penalty based on status
        if (in_array($appointment->status, ['no_show','skipped'], true)) {
            $this->applyPenalty($appointment, $appointment->status);
        } elseif ($appointment->status === 'completed') {
            // Placeholder: decrement remaining sessions, mark professional as paid, etc.
            AppointmentAudit::record($appointment, 'plan_session_consumed', 'completed', 'completed');
            Log::info('plan.session.consumed', ['appointment_id' => $appointment->id]);
        }
    }

    protected function applyPenalty(Appointment $appointment, string $type): void
    {
        if ($appointment->penalty_applied_at) return; // already applied
        $appointment->penalty_applied_at = now();
        $appointment->penalty_type = $type;
        $appointment->save();
        AppointmentAudit::record($appointment, 'plan_penalty_applied', $appointment->status, $appointment->status, [ 'penalty_type' => $type ]);
        Log::warning('plan.penalty.applied', ['appointment_id' => $appointment->id, 'type' => $type]);
    }

    public function applyPendingPenalties(): int
    {
        $count = 0;
        $pending = \App\Models\Appointment::whereIn('status',['no_show','skipped'])
            ->whereNull('penalty_applied_at')
            ->get();
        foreach ($pending as $appt) {
            $this->applyPenalty($appt, $appt->status);
            $count++;
        }
        return $count;
    }
}

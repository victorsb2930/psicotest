<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Services\AppointmentSessionService;
use App\Models\AppointmentAudit;
use App\Events\AppointmentRescheduled;
use App\Events\AppointmentSkipped;
use App\Events\AppointmentCompleted;

class FinalizeAppointmentsCommand extends Command
{
    protected $signature = 'appointments:finalize';
    protected $description = 'Finalize ended appointments (complete/no_show/skipped) and expire pending reschedules';

    public function handle(AppointmentSessionService $service): int
    {
        $now = now();
        $thresholdHours = (int) config('appointments.unanswered_reprogram_hours');

        // Expire reschedules nearing start (unanswered within threshold window)
        $reschedules = AppointmentReschedule::where('status','pending')->with('appointment')->get();
        foreach ($reschedules as $res) {
            $appt = $res->appointment; if (!$appt) continue;
            if ($appt->status === 'reschedule_pending') {
                $cutoff = $appt->start->copy()->subHours($thresholdHours);
                if ($now->greaterThanOrEqualTo($cutoff)) {
                    $res->status = 'expired';
                    $res->responded_at = $now;
                    $res->save();
                    // Mark appointment skipped (simplified rule)
                    $from = $appt->status;
                    $appt->status = 'skipped';
                    $appt->save();
                    AppointmentAudit::record($appt, 'reschedule_expired', $from, $appt->status, [ 'reschedule_id' => $res->id ]);
                    event(new AppointmentRescheduled($appt, $res));
                    event(new AppointmentSkipped($appt));
                    $this->info("Reschedule expired -> appointment {$appt->id} marked skipped");
                }
            }
        }

        // Finalize ended sessions
        $query = Appointment::whereIn('status',['accepted','in_progress'])
            ->where('end','<',$now)
            ->get();
        foreach ($query as $appointment) {
            $from = $appointment->status;
            $service->complete($appointment);
            // Audit already recorded inside service; just emit info
            if ($appointment->status === 'completed') {
                // Event dispatched in service
            } else {
                // skipped/no_show already dispatched
            }
            $this->info("Appointment {$appointment->id} finalized from {$from} to {$appointment->status}");
        }

        return Command::SUCCESS;
    }
}

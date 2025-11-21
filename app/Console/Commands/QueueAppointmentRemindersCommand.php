<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Jobs\AppointmentReminderJob;
use Illuminate\Support\Facades\Cache;

class QueueAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:queue-reminders';
    protected $description = 'Dispatch reminder jobs for upcoming appointments';

    public function handle(): int
    {
        $now = now();
        // Look ahead up to 4 days
        $appts = Appointment::whereIn('status',['accepted','in_progress','reschedule_pending'])
            ->where('start','>', $now)
            ->where('start','<', $now->copy()->addDays(4))
            ->get();

        foreach ($appts as $appt) {
            $targets = [
                '3d' => $appt->start->copy()->subDays(3),
                '2d' => $appt->start->copy()->subDays(2),
                '24h' => $appt->start->copy()->subHours(24),
                '5h' => $appt->start->copy()->subHours(5),
            ];
            foreach ($targets as $type => $ts) {
                if ($now->greaterThanOrEqualTo($ts) && $appt->start->greaterThan($now)) {
                    $cacheKey = 'appt:'.$appt->id.':reminder:'.$type;
                    if (!Cache::has($cacheKey)) {
                        Cache::put($cacheKey, true, $appt->start->addDays(2));
                        dispatch(new AppointmentReminderJob($appt, $type));
                        $this->info("Reminder {$type} queued for appointment {$appt->id}");
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}

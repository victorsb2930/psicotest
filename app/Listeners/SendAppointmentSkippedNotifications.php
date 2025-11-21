<?php

namespace App\Listeners;

use App\Events\AppointmentSkipped;
use App\Notifications\AppointmentSkippedNotification;
use App\Notifications\AppointmentNoShowNotification;

class SendAppointmentSkippedNotifications
{
    public function handle(AppointmentSkipped $event): void
    {
        $appt = $event->appointment;
        if ($appt->status === 'no_show') {
            // Notify both plus differentiate if needed (could refine later)
            try { $appt->professional?->notify(new AppointmentNoShowNotification($appt)); } catch (\Throwable $e) {}
            try { $appt->patient?->notify(new AppointmentNoShowNotification($appt)); } catch (\Throwable $e) {}
        } else {
            try { $appt->professional?->notify(new AppointmentSkippedNotification($appt)); } catch (\Throwable $e) {}
            try { $appt->patient?->notify(new AppointmentSkippedNotification($appt)); } catch (\Throwable $e) {}
        }
    }
}

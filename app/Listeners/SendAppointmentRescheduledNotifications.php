<?php

namespace App\Listeners;

use App\Events\AppointmentRescheduled;
use App\Notifications\AppointmentRescheduledNotification;

class SendAppointmentRescheduledNotifications
{
    public function handle(AppointmentRescheduled $event): void
    {
        $appt = $event->appointment;
        $res = $event->reschedule;
        try { $appt->professional?->notify(new AppointmentRescheduledNotification($appt, $res)); } catch (\Throwable $e) {}
        try { $appt->patient?->notify(new AppointmentRescheduledNotification($appt, $res)); } catch (\Throwable $e) {}
    }
}

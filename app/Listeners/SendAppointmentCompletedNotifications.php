<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;
use Illuminate\Support\Facades\Log;

class SendAppointmentCompletedNotifications
{
    public function handle(AppointmentCompleted $event): void
    {
        // Placeholder: could trigger post-completion actions (rating prompt) later.
        try { Log::info('appointment.completed.listener', ['appointment_id' => $event->appointment->id]); } catch (\Throwable $e) {}
    }
}

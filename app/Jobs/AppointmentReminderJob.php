<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\AppointmentAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $type; // 3d, 2d, 24h, 5h

    public function __construct(public Appointment $appointment, string $type)
    {
        $this->type = $type;
    }

    public function handle(): void
    {
        $appt = $this->appointment->fresh();
        if (!$appt) return;
        // Only accepted or in_progress appointments get reminders (pending are waiting approval)
        if (!in_array($appt->status, ['accepted','in_progress','reschedule_pending'], true)) return;

        $title = match($this->type) {
            '3d' => 'Recordatorio cita (3 días)',
            '2d' => 'Recordatorio cita (2 días)',
            '24h' => 'Recordatorio cita (24 horas)',
            '5h' => 'Cita próxima (5 horas)',
            default => 'Recordatorio cita'
        };
        $meta = [ 'type' => $this->type, 'start' => optional($appt->start)->toIso8601String() ];

        // Minimal audit trail
        AppointmentAudit::record($appt, 'reminder_'.$this->type, $appt->status, $appt->status, $meta);

        // If notifications table exists and user model supports notifications, create simple notifications (optional)
        try {
            if (Schema::hasTable('notifications')) {
                $prof = $appt->professional()->first();
                $pat = $appt->patient()->first();
                foreach ([$prof,$pat] as $user) {
                    if (!$user) continue;
                    try { $user->notify(new \App\Notifications\GenericAppointmentReminder($title, $appt)); } catch (\Throwable $e) { /* ignore */ }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        Log::info('appointment.reminder.dispatched', [ 'appointment_id' => $appt->id, 'type' => $this->type ]);
    }
}

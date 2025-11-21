<?php

namespace App\Events;

use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment, public AppointmentReschedule $reschedule) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('appointments.'. $this->appointment->professional_id),
            new PrivateChannel('appointments.'. $this->appointment->patient_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->appointment->id,
            'status' => $this->appointment->status,
            'reschedule_id' => $this->reschedule->id,
            'reschedule_status' => $this->reschedule->status,
            'proposed_start' => optional($this->reschedule->proposed_start)->toIso8601String(),
            'proposed_end' => optional($this->reschedule->proposed_end)->toIso8601String(),
        ];
    }
}

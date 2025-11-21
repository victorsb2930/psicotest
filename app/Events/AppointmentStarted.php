<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentStarted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

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
            'room_id' => $this->appointment->room_id,
            'start' => optional($this->appointment->start)->toIso8601String(),
            'end' => optional($this->appointment->end)->toIso8601String(),
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentEndCancelled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment, public int $cancellerId)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('appointments.' . $this->appointment->professional_id),
            new PrivateChannel('appointments.' . $this->appointment->patient_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => (int) $this->appointment->id,
            'canceller_id' => (int) $this->cancellerId,
            'type' => 'cancel_end_request',
        ];
    }
}

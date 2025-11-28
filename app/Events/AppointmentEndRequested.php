<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentEndRequested implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param Appointment $appointment
     * @param int $requesterId
     */
    public function __construct(public Appointment $appointment, public int $requesterId)
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
            'requester_id' => (int) $this->requesterId,
            'type' => 'request_end_session',
        ];
    }
}

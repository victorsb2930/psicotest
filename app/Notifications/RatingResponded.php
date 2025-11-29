<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\Channel;
use App\Models\AppointmentRating;

class RatingResponded extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(public AppointmentRating $rating) {}

    public function via($notifiable): array
    {
        return ['database','mail','broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Respuesta a tu calificación')
            ->greeting('Hola '.$notifiable->name)
            ->line('El profesional ha respondido a tu reseña.')
            ->when($this->rating->response_text, fn($m) => $m->line('Respuesta: "'.$this->rating->response_text.'"'))
            ->action('Ver cita', url('/appointments/'.$this->rating->appointment_id))
            ->line('Gracias por usar la plataforma.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'rating_responded',
            'appointment_id' => $this->rating->appointment_id,
            'rating_id' => $this->rating->id,
            'response_text' => $this->rating->response_text,
        ];
    }

    public function broadcastOn()
    {
        // Broadcast to the private user channel of the patient (note: channels.php registers 'user.{id}')
        return new PrivateChannel('user.'.$this->rating->patient_id);
    }

    public function broadcastWith()
    {
        return $this->toArray(null);
    }
}

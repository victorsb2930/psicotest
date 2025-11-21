<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\AppointmentRating;

class RatingSubmitted extends Notification
{
    use Queueable;

    public function __construct(public AppointmentRating $rating) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva calificación recibida')
            ->greeting('Hola '.$notifiable->name)
            ->line('Has recibido una nueva calificación de un paciente.')
            ->line('Puntaje: '.$this->rating->rating.'/5')
            ->when($this->rating->comment, fn($m) => $m->line('Comentario: "'.$this->rating->comment.'"'))
            ->action('Ver reseñas', url('/professional/ratings'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'rating_submitted',
            'appointment_id' => $this->rating->appointment_id,
            'rating_id' => $this->rating->id,
            'score' => $this->rating->rating,
            'comment' => $this->rating->comment,
        ];
    }
}

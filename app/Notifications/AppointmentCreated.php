<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentCreated extends Notification
{
    use Queueable;

    protected $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Nueva cita recibida')
                    ->greeting('Hola '.$notifiable->name)
                    ->line('Has recibido una nueva invitación a cita.')
                    ->line('Inicio: '.$this->appointment->start)
                    ->line('Fin: '.($this->appointment->end ?? '—'))
                    ->action('Ver cita', url('/'))
                    ->line('Si no reconoces esta cita, ignora este mensaje.');
    }
}

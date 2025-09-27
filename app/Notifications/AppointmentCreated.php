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
        // Send both mail and database notification so professionals get an in-app notification
        return ['mail', 'database'];
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

    /**
     * Data stored for the database notification channel
     */
    public function toArray($notifiable)
    {
        return [
            'type' => 'appointment_created',
            'appointment_id' => $this->appointment->id,
            'title' => $this->appointment->title,
            'start' => $this->appointment->start,
            'end' => $this->appointment->end,
            'patient_id' => $this->appointment->patient_id,
            'patient_name' => $this->appointment->patient?->name,
            'notes' => $this->appointment->notes ?? null,
        ];
    }
}

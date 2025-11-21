<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentSkippedNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cita marcada como omitida')
            ->greeting('Hola '.$notifiable->name)
            ->line('La cita #'.$this->appointment->id.' fue marcada como omitida (skipped).')
            ->line('Inicio: '.optional($this->appointment->start)->format('d/m/Y H:i'))
            ->action('Ver cita', url('/appointments/'.$this->appointment->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'appointment_skipped',
            'appointment_id' => $this->appointment->id,
            'status' => $this->appointment->status,
            'start' => optional($this->appointment->start)->toIso8601String(),
            'end' => optional($this->appointment->end)->toIso8601String(),
        ];
    }
}

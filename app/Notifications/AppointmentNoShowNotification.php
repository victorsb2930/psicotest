<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentNoShowNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ausencia en cita (No-show)')
            ->greeting('Hola '.$notifiable->name)
            ->line('La cita #'.$this->appointment->id.' se marcó como no-show.')
            ->line('Inicio: '.optional($this->appointment->start)->format('d/m/Y H:i'))
            ->action('Ver detalles', url('/appointments/'.$this->appointment->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'appointment_no_show',
            'appointment_id' => $this->appointment->id,
            'status' => $this->appointment->status,
            'start' => optional($this->appointment->start)->toIso8601String(),
            'end' => optional($this->appointment->end)->toIso8601String(),
        ];
    }
}

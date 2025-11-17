<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentCancelled extends Notification
{
    use Queueable;

    protected $appointment;
    protected $reason;

    public function __construct(Appointment $appointment, $reason = null)
    {
        $this->appointment = $appointment;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        // Notify via mail and database for in-app visibility
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('Cita cancelada por el paciente')
            ->greeting('Hola '.$notifiable->name)
            ->line('El paciente ha cancelado la solicitud de cita.')
            ->line('Inicio: '.$this->appointment->start)
            ->line('Fin: '.($this->appointment->end ?? '—'))
            ->action('Ver cita', url('/'));
        if (!empty($this->reason)) {
            $mail->line('Motivo: '.$this->reason);
        }
        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'appointment_cancelled',
            'appointment_id' => $this->appointment->id,
            'title' => $this->appointment->title,
            'start' => $this->appointment->start,
            'end' => $this->appointment->end,
            'reason' => $this->reason ?? null,
            'patient_id' => $this->appointment->patient_id,
            'professional_id' => $this->appointment->professional_id,
        ];
    }
}

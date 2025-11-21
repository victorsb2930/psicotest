<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;
use App\Models\AppointmentReschedule;

class AppointmentRescheduledNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment, public AppointmentReschedule $reschedule) {}

    public function via($notifiable): array
    {
        return ['database','mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $status = $this->reschedule->status;
        $subject = match($status) {
            'pending' => 'Solicitud de reprogramación de cita',
            'accepted' => 'Reprogramación de cita aceptada',
            'rejected' => 'Reprogramación de cita rechazada',
            'expired' => 'Solicitud de reprogramación expirada',
            default => 'Actualización de reprogramación de cita'
        };
        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting('Hola '.$notifiable->name)
            ->line('Estado: '.ucfirst($status))
            ->line('Cita #'.$this->appointment->id)
            ->line('Anterior inicio: '.optional($this->appointment->start)->format('d/m/Y H:i'));
        if ($this->reschedule->proposed_start) {
            $msg->line('Nuevo inicio propuesto: '.optional($this->reschedule->proposed_start)->format('d/m/Y H:i'));
        }
        if ($this->reschedule->proposed_end) {
            $msg->line('Nuevo fin propuesto: '.optional($this->reschedule->proposed_end)->format('d/m/Y H:i'));
        }
        return $msg->action('Ver cita', url('/appointments/'.$this->appointment->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'appointment_rescheduled',
            'appointment_id' => $this->appointment->id,
            'reschedule_id' => $this->reschedule->id,
            'reschedule_status' => $this->reschedule->status,
            'proposed_start' => optional($this->reschedule->proposed_start)->toIso8601String(),
            'proposed_end' => optional($this->reschedule->proposed_end)->toIso8601String(),
        ];
    }
}

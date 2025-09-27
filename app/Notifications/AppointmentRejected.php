<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentRejected extends Notification
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
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Cita rechazada')
                    ->greeting('Hola '.$notifiable->name)
                    ->line('Tu cita ha sido rechazada.')
                    ->line('Inicio: '.$this->appointment->start)
                    ->line('Fin: '.($this->appointment->end ?? '—'))
                    ->when(!empty($this->reason), function($msg){
                        return $msg->line('Motivo: '.$this->reason);
                    })
                    ->action('Ver cita', url('/'));
    }

    

    public function toArray($notifiable)
    {
        return [
            'type' => 'appointment_rejected',
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

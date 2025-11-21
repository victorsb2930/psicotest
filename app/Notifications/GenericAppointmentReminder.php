<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class GenericAppointmentReminder extends Notification
{
    use Queueable;

    public function __construct(public string $title, public Appointment $appointment) {}

    public function via($notifiable): array
    {
        return ['database']; // mail optional later
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => 'Tu cita comienza el '.optional($this->appointment->start)->format('d/m/Y H:i'),
            'icon' => 'calendar-event',
            'link' => '#',
            'appointment_id' => $this->appointment->id,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line('Tu cita está programada para el '.optional($this->appointment->start)->format('d/m/Y H:i'))
            ->action('Ver cita', url('/professional/calendar'));
    }
}

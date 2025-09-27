<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DeviceSuspiciousAttempt extends Notification
{
    use Queueable;

    protected $ip;
    protected $ua;

    public function __construct($ip, $ua)
    {
        $this->ip = $ip;
        $this->ua = $ua;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Intento sospechoso de dispositivo')
            ->line('Detectamos un intento de reutilización de token desde un dispositivo que no coincide con tu navegador conocido.')
            ->line('IP: ' . ($this->ip ?? 'desconocida'))
            ->line('User-Agent: ' . (strlen($this->ua ?? '') ? substr($this->ua,0,200) : 'desconocido'))
            ->line('Si no fuiste tú, revisa tus dispositivos activos en la configuración de tu cuenta y revoca acceso.');
    }
}

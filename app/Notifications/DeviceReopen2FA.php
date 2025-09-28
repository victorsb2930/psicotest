<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DeviceReopen2FA extends Notification
{
    use Queueable;

    protected $code;
    protected $ip;
    protected $ua;

    public function __construct(string $code, ?string $ip = null, ?string $ua = null)
    {
        $this->code = $code;
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
            ->subject('Código 2FA para reabrir sesión')
            ->line('Detectamos un intento de reutilización de token desde otra dirección IP.')
            ->line('Si eres tú y el navegador coincide, usa este código para confirmar la reapertura de la sesión:')
            ->line('Código: ' . $this->code)
            ->line('IP: ' . ($this->ip ?? 'desconocida'))
            ->line('User-Agent: ' . (strlen($this->ua ?? '') ? substr($this->ua,0,200) : 'desconocido'))
            ->line('Este código expira en 10 minutos. Si no reconoces esta actividad, revoca los dispositivos activos en tu cuenta.');
    }
}

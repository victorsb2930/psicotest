<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => '¡Bienvenido(a) a PsicoGuía!',
            'body' => 'Tu cuenta está lista. Explora el chat y tus opciones.',
            'icon' => 'heart',
            'link' => '/',
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestAcceptedNotification extends Notification
{
    use Queueable;

    protected $byUser;

    public function __construct($byUser)
    {
        $this->byUser = $byUser;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $u = $this->byUser;
        $name = $u ? ($u->name . (isset($u->lastname) ? (' ' . $u->lastname) : '')) : 'El usuario';
        return [
            'title' => 'Solicitud aceptada',
            'body' => $name . ' aceptó tu solicitud de contacto.',
            'icon' => 'person-check',
            'link' => route('chat.index'),
            'by_id' => $u?->id,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestReceived extends Notification
{
    use Queueable;

    protected $fromUser;

    public function __construct($fromUser)
    {
        $this->fromUser = $fromUser;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $from = $this->fromUser;
        $fromName = $from ? ($from->name . (isset($from->lastname) ? (' ' . $from->lastname) : '')) : 'Un usuario';
        return [
            'title' => 'Nueva solicitud de contacto',
            'body' => $fromName . ' te envió una solicitud de contacto.',
            'icon' => 'person-plus',
            'link' => route('chat.index'),
            'from_id' => $from?->id,
        ];
    }
}

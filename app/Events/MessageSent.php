<?php
namespace App\Events;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class MessageSent implements ShouldBroadcastNow {
    use InteractsWithSockets, SerializesModels;
    public function __construct(public Message $message){}
    public function broadcastOn(): array { return [new PrivateChannel('user.'.$this->message->to_id)]; }
    public function broadcastWith(): array { return ['id'=>$this->message->id,'from_id'=>$this->message->from_id,'from_name'=>$this->message->from?->name,'body'=>$this->message->body,'created_at'=>$this->message->created_at?->toIso8601String()]; }
}
<?php
namespace App\Events;
use App\Models\FriendRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
class FriendRequestSent implements ShouldBroadcast {
    use InteractsWithSockets, SerializesModels;
    public function __construct(public FriendRequest $request){}
    public function broadcastOn(): array { return [new PrivateChannel('user.'.$this->request->to_id)]; }
    public function broadcastWith(): array { return ['id'=>$this->request->id,'from_id'=>$this->request->from_id,'from_name'=>$this->request->from?->name]; }
}
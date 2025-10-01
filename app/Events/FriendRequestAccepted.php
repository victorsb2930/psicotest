<?php
namespace App\Events;
use App\Models\FriendRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
class FriendRequestAccepted implements ShouldBroadcast {
    use InteractsWithSockets, SerializesModels;
    public function __construct(public FriendRequest $request){}
    public function broadcastOn(): array { return [new PrivateChannel('user.'.$this->request->from_id)]; }
    public function broadcastWith(): array { return ['id'=>$this->request->id,'to_id'=>$this->request->to_id,'to_name'=>$this->request->to?->name]; }
}
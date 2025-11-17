<?php
namespace App\Http\Controllers;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\FriendRequestSent;
use App\Events\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\FriendRequestAcceptedNotification;
class FriendRequestController extends Controller {
    public function send(Request $r, User $user){
        $me = $r->user();
        if ($me->id === $user->id) return response()->json(['ok'=>false,'error'=>'same_user'],400);
        $fr = FriendRequest::firstOrCreate(['from_id'=>$me->id,'to_id'=>$user->id],[]);
        if ($fr->wasRecentlyCreated) {
            try { broadcast(new FriendRequestSent($fr->load('from')))->toOthers(); } catch (\Throwable $_) {}
            // Database notification to the recipient
            try { $user->notify(new FriendRequestReceived($me)); } catch (\Throwable $_) {}
        }
        return response()->json(['ok'=>true,'status'=>$fr->status]);
    }
    public function accept(Request $r, FriendRequest $requestModel){
        $me = $r->user();
        // to_id may come as string from the database; cast to int to avoid
        // strict comparison mismatches that incorrectly abort with 403.
        if (((int) $requestModel->to_id) !== (int) $me->id) abort(403);
        $requestModel->status='accepted'; $requestModel->accepted_at=now(); $requestModel->save();
        // ensure relationships are loaded so we can return friend info
        $requestModel->load('from','to');
        // Notify the original sender that their request was accepted
        try { broadcast(new FriendRequestAccepted($requestModel))->toOthers(); } catch(\Throwable $_) {}
        // Notify the original sender via database notification
        try { $requestModel->from?->notify(new FriendRequestAcceptedNotification($me)); } catch(\Throwable $_) {}

        // Return minimal friend data so the client can update the UI without a full reload
        $friend = $requestModel->from;
        $payload = ['ok' => true, 'friend' => null];
        if ($friend) {
            $payload['friend'] = ['id' => $friend->id, 'name' => $friend->name, 'email' => $friend->email];
        }
        return response()->json($payload);
    }
    public function reject(Request $r, FriendRequest $requestModel){
        $me = $r->user();
        // Ensure identities match after casting to int to avoid type issues.
        if (((int) $requestModel->to_id) !== (int) $me->id) abort(403);
        $requestModel->status='rejected'; $requestModel->rejected_at=now(); $requestModel->save();
        return response()->json(['ok'=>true]);
    }
    public function pending(Request $r){
        $me = $r->user();
        $reqs = FriendRequest::with('from')->where('to_id',$me->id)->where('status','pending')->get();
        return response()->json(['ok'=>true,'requests'=>$reqs]);
    }
}
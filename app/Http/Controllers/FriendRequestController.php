<?php
namespace App\Http\Controllers;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\FriendRequestSent;
use App\Events\FriendRequestAccepted;
class FriendRequestController extends Controller {
    public function send(Request $r, User $user){
        $me = $r->user();
        if ($me->id === $user->id) return response()->json(['ok'=>false,'error'=>'same_user'],400);
        $fr = FriendRequest::firstOrCreate(['from_id'=>$me->id,'to_id'=>$user->id],[]);
        if ($fr->wasRecentlyCreated) { broadcast(new FriendRequestSent($fr->load('from')))->toOthers(); }
        return response()->json(['ok'=>true,'status'=>$fr->status]);
    }
    public function accept(Request $r, FriendRequest $requestModel){
        $me = $r->user();
        if ($requestModel->to_id !== $me->id) abort(403);
        $requestModel->status='accepted'; $requestModel->accepted_at=now(); $requestModel->save();
        broadcast(new FriendRequestAccepted($requestModel->load('to')))->toOthers();
        return response()->json(['ok'=>true]);
    }
    public function reject(Request $r, FriendRequest $requestModel){
        $me = $r->user();
        if ($requestModel->to_id !== $me->id) abort(403);
        $requestModel->status='rejected'; $requestModel->rejected_at=now(); $requestModel->save();
        return response()->json(['ok'=>true]);
    }
    public function pending(Request $r){
        $me = $r->user();
        $reqs = FriendRequest::with('from')->where('to_id',$me->id)->where('status','pending')->get();
        return response()->json(['ok'=>true,'requests'=>$reqs]);
    }
}
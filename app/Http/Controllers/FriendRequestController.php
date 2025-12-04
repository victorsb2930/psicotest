<?php
namespace App\Http\Controllers;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\FriendRequestSent;
use App\Events\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\FriendRequestAcceptedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class FriendRequestController extends Controller {
    protected array $chatStatuses = ['accepted','in_progress','completed'];

    protected function shareAcceptedAppointment(User $a, User $b): bool
    {
        if (!Schema::hasTable('appointments')) {
            return false;
        }

        $statuses = array_map('strtolower', $this->chatStatuses);

        return DB::table('appointments')
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), $statuses)
            ->where(function($q) use ($a, $b) {
                $q->where(function($sub) use ($a, $b) {
                    $sub->where('professional_id', $a->id)->where('patient_id', $b->id);
                })->orWhere(function($sub) use ($a, $b) {
                    $sub->where('professional_id', $b->id)->where('patient_id', $a->id);
                });
            })
            ->exists();
    }

    public function send(Request $r, User $user){
        $me = $r->user();
        if ($me->id === $user->id) {
            return response()->json(['ok'=>false,'error'=>'same_user'],400);
        }
        if (!Schema::hasTable('friend_requests')) {
            return response()->json(['ok'=>false,'error'=>'unavailable'],503);
        }

        // Role gating: only allow cross-role pairing (user<->professional)
        try {
            $myRoles = method_exists($me, 'roles') ? $me->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() : [];
            $targetRoles = method_exists($user, 'roles') ? $user->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() : [];
            $is2 = in_array(2, $myRoles, true); $is3 = in_array(3, $myRoles, true);
            $targetIs2 = in_array(2, $targetRoles, true); $targetIs3 = in_array(3, $targetRoles, true);
            $validPair = ($is2 && $targetIs3) || ($is3 && $targetIs2);
            if (!$validPair) {
                return response()->json(['ok'=>false,'error'=>'role_mismatch','message'=>'Solo puedes agregar contactos del rol complementario.'],403);
            }
        } catch (\Throwable $_) {}

        if (!$this->shareAcceptedAppointment($me, $user)) {
            return response()->json([
                'ok'=>false,
                'error'=>'missing_appointment',
                'message'=>'Solo puedes agregar contactos con quienes ya tengas una cita aceptada.',
            ], 403);
        }

        $pendingExists = FriendRequest::query()
            ->where(function($q) use ($me, $user) {
                $q->where([['from_id',$me->id],['to_id',$user->id]])
                  ->orWhere([['from_id',$user->id],['to_id',$me->id]]);
            })
            ->where('status','pending')
            ->exists();
        if ($pendingExists) {
            return response()->json(['ok'=>true,'duplicate'=>true]);
        }

        $fr = FriendRequest::firstOrNew(['from_id'=>$me->id,'to_id'=>$user->id]);
        $shouldNotify = false;
        if (!$fr->exists) {
            $fr->status = 'pending';
            $fr->save();
            $shouldNotify = true;
        } else {
            if ($fr->status !== 'pending') {
                $fr->status = 'pending';
                $fr->accepted_at = null; $fr->rejected_at = null;
                $fr->save();
                $shouldNotify = true;
            }
        }
        if ($shouldNotify) {
            try { broadcast(new FriendRequestSent($fr->load('from')))->toOthers(); } catch (\Throwable $_) {}
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

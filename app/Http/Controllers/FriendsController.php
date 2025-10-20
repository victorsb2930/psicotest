<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\FriendRequest;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class FriendsController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        // Friends (accepted)
        $friendIds = FriendRequest::query()
            ->where(function($q) use ($me){ $q->where('from_id',$me->id)->orWhere('to_id',$me->id); })
            ->where('status','accepted')
            ->selectRaw('CASE WHEN from_id = ? THEN to_id ELSE from_id END as fid', [$me->id]);
        $friends = User::whereIn('id', $friendIds)->orderBy('name')->limit(200)->get();

        // Pending incoming
        $incoming = FriendRequest::with('from')
            ->where('to_id',$me->id)->where('status','pending')->orderByDesc('id')->get();
        // Pending outgoing
        $outgoing = FriendRequest::with('to')
            ->where('from_id',$me->id)->where('status','pending')->orderByDesc('id')->get();

        $unreadMessages = Message::where('to_id',$me->id)->whereNull('read_at')->count();
        $pendingRequests = $incoming->count();

        return view('friends.index', compact('friends','incoming','outgoing','unreadMessages','pendingRequests'));
    }

    public function search(Request $request)
    {
        $me = $request->user();
        $q = trim((string)$request->input('q',''));

        // Determine allowed counterpart role: if current is role 2 -> target 3; if current is role 3 -> target 2
        $currentRoleIds = [];
        try { $currentRoleIds = $me ? $me->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() : []; } catch (\Throwable $_) { $currentRoleIds = []; }
        $isType2 = in_array(2, $currentRoleIds, true);
        $isType3 = in_array(3, $currentRoleIds, true);
        $allowedTargetRole = null;
        if ($isType2 && ! $isType3) $allowedTargetRole = 3;
        elseif ($isType3 && ! $isType2) $allowedTargetRole = 2;

        // If current user is neither pure type2 nor pure type3, return empty results (no cross-role search)
        if (is_null($allowedTargetRole)) {
            return response()->json(['ok' => true, 'results' => collect(), 'query' => $q, 'excluded_count' => 0, 'allowed_target_role' => null]);
        }

        // Gather related user ids (friends in any status) + self
        $relatedIds = [];
        FriendRequest::query()
            ->where(function($w) use ($me){ $w->where('from_id',$me->id)->orWhere('to_id',$me->id); })
            ->chunkById(200, function($chunk) use (&$relatedIds, $me){
                foreach($chunk as $fr){
                    if ($fr->from_id !== $me->id) $relatedIds[] = $fr->from_id;
                    if ($fr->to_id !== $me->id) $relatedIds[] = $fr->to_id;
                }
            });
        $relatedIds[] = $me->id;
        $relatedIds = array_values(array_unique($relatedIds));

        $driver = \DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

    $query = User::query()->whereNotIn('id', $relatedIds)->whereHas('roles', function($w) use ($allowedTargetRole){ $w->where('id', $allowedTargetRole); });
        if ($q !== '') {
            $query->where(function($w) use ($q, $likeOp){
                $w->where('name', $likeOp, '%'.$q.'%')
                  ->orWhere('email', $likeOp, '%'.$q.'%');
            });
        }

        // If no search term, return first suggestions (limited)
        $users = $query->orderBy('name')->limit(20)->get(['id','name','email']);

        return response()->json([
            'ok'=>true,
            'results'=>$users,
            'query'=>$q,
            'excluded_count'=>count($relatedIds)
        ]);
    }
}

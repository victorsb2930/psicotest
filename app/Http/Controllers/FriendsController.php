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
        if ($q === '') return response()->json(['ok'=>true,'results'=>[]]);
        $rel = FriendRequest::query()
            ->where(function($w) use ($me){ $w->where('from_id',$me->id)->orWhere('to_id',$me->id); })
            ->pluck('from_id','id')->values()->merge(
                FriendRequest::query()->where(function($w) use ($me){ $w->where('from_id',$me->id)->orWhere('to_id',$me->id); })
                ->pluck('to_id')->values()
            )->push($me->id)->unique()->all();

        $users = User::query()
            ->whereNotIn('id', $rel)
            ->where(function($w) use ($q){
                $w->where('name','ilike',"%$q%")
                  ->orWhere('email','ilike',"%$q%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name','email']);
        return response()->json(['ok'=>true,'results'=>$users]);
    }
}

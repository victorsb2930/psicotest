<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\MessageSent;

class MessagesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // List last message per conversation partner
        $partners = Message::query()
            ->where(function($q) use ($user){
                $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
            })
            ->select(DB::raw('CASE WHEN from_id = '.$user->id.' THEN to_id ELSE from_id END as partner_id'), DB::raw('MAX(id) as last_id'))
            ->groupBy('partner_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->pluck('last_id');
        $lastMessages = Message::with(['from','to'])->whereIn('id', $partners)->orderByDesc('id')->get();

        return view('messages.index', [
            'lastMessages' => $lastMessages,
        ]);
    }

    public function thread(Request $request, User $user)
    {
        $me = $request->user();
        if ($me->id === $user->id) {
            abort(404);
        }
        $messages = Message::with(['from','to'])
            ->where(function($q) use ($me, $user){
                $q->where('from_id', $me->id)->where('to_id', $user->id);
            })
            ->orWhere(function($q) use ($me, $user){
                $q->where('from_id', $user->id)->where('to_id', $me->id);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        // mark as read
        Message::whereNull('read_at')
            ->where('to_id', $me->id)
            ->where('from_id', $user->id)
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok'=>true,'messages'=>$messages]);
        }

        return view('messages.thread', [
            'partner' => $user,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, User $user)
    {
        $me = $request->user();
        if ($me->id === $user->id) {
            return response()->json(['ok'=>false,'error'=>'same_user'], 400);
        }
        // Enforce friendship (accepted friend request in either direction)
        $areFriends = \App\Models\FriendRequest::where(function($q) use ($me,$user){
            $q->where('from_id',$me->id)->where('to_id',$user->id);
        })->orWhere(function($q) use ($me,$user){
            $q->where('from_id',$user->id)->where('to_id',$me->id);
        })->where('status','accepted')->exists();
        if (!$areFriends) {
            return response()->json(['ok'=>false,'error'=>'not_friends'], 403);
        }
        $data = $request->validate([
            'body' => 'required|string|max:4000'
        ]);

        $msg = Message::create([
            'from_id' => $me->id,
            'to_id' => $user->id,
            'body' => $data['body'],
        ]);

        $msg->load(['from','to']);

        try { broadcast(new MessageSent($msg))->toOthers(); } catch (\Throwable $e) { /* ignore if broadcasting not configured */ }

        return response()->json(['ok'=>true,'message'=>$msg]);
    }
}

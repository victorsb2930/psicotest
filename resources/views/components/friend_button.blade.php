@php
$me = auth()->user();
/** @var \App\Models\User $user */
// $user viene desde contexto donde se incluya
$friendReq = null; $friendStatus = null; $isSame = $me && $user && $me->id === $user->id;
if($me && $user && !$isSame) {
    $friendReq = \App\Models\FriendRequest::where(function($q) use ($me,$user){
        $q->where('from_id',$me->id)->where('to_id',$user->id);
    })->orWhere(function($q) use ($me,$user){
        $q->where('from_id',$user->id)->where('to_id',$me->id);
    })->first();
    $friendStatus = $friendReq?->status;
}
@endphp
@if($me && $user && !$isSame)
    <div id="friend-action" data-user="{{ $user->id }}">
        @if(!$friendReq)
            <button class="btn btn-sm btn-outline-primary" id="btn-send-friend">Agregar amigo</button>
        @elseif($friendStatus === 'pending' && $friendReq->to_id === $me->id)
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" id="btn-accept-friend" data-fr="{{ $friendReq->id }}">Aceptar</button>
                <button class="btn btn-sm btn-outline-danger" id="btn-reject-friend" data-fr="{{ $friendReq->id }}">Rechazar</button>
            </div>
        @elseif($friendStatus === 'pending')
            <span class="badge text-bg-warning">Solicitud enviada</span>
        @elseif($friendStatus === 'accepted')
            <span class="badge text-bg-success">Amigos</span>
        @elseif($friendStatus === 'rejected')
            <button class="btn btn-sm btn-outline-primary" id="btn-send-friend">Reintentar amistad</button>
        @endif
    </div>
    @push('scripts')
    <script>
    (function(){
        const wrap = document.getElementById('friend-action'); if(!wrap) return; const uid = wrap.getAttribute('data-user');
        async function post(url){
            const res = await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
            return res.json();
        }
        function refresh(){ location.reload(); }
        wrap.addEventListener('click', async e => {
            const t = e.target;
            if (t.id === 'btn-send-friend') { e.preventDefault(); const j = await post(`/friend/${uid}/request`); if(j.ok){ refresh(); } }
            if (t.id === 'btn-accept-friend') { e.preventDefault(); const id = t.getAttribute('data-fr'); const j = await post(`/friend/request/${id}/accept`); if(j.ok){ refresh(); } }
            if (t.id === 'btn-reject-friend') { e.preventDefault(); const id = t.getAttribute('data-fr'); const j = await post(`/friend/request/${id}/reject`); if(j.ok){ refresh(); } }
        });
        window.addEventListener('rt:friend_request', ev => { const d=ev.detail; if (parseInt(d.id) && d.from_id == uid) { /* could update UI */ } });
        window.addEventListener('rt:friend_request_accepted', ev => { const d=ev.detail; if (d.to_id == uid) { /* update UI */ } });
    })();
    </script>
    @endpush
@endif
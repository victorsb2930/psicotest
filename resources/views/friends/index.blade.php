@extends('layouts.app')
@section('title','Amigos')
@section('content')
<div class="container py-3" id="friends-page">
    <h1 class="h4 mb-4 d-flex align-items-center gap-2">Amigos <small class="text-muted fw-normal">( {{ $friends->count() }} )</small></h1>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Buscar usuarios</div>
                <div class="card-body">
                    <input id="friend-search" class="form-control mb-2" placeholder="Nombre o email" autocomplete="off">
                    <div id="friend-search-results" class="list-group small" style="max-height:200px; overflow:auto;"></div>
                    <div class="form-text">Solo se muestran usuarios con los que no tienes relación.</div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Solicitudes entrantes ({{ $incoming->count() }})</div>
                <div class="list-group list-group-flush" id="incoming-list">
                    @forelse($incoming as $req)
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $req->from->name }}</div>
                                <div class="text-muted small">{{ $req->from->email }}</div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success btn-accept" data-id="{{ $req->id }}">Aceptar</button>
                                <button class="btn btn-outline-danger btn-reject" data-id="{{ $req->id }}">Rechazar</button>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">Sin solicitudes.</div>
                    @endforelse
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Solicitudes enviadas ({{ $outgoing->count() }})</div>
                <div class="list-group list-group-flush">
                    @forelse($outgoing as $req)
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $req->to->name }}</div>
                                <div class="text-muted small">{{ $req->to->email }}</div>
                            </div>
                            <span class="badge text-bg-warning">Pendiente</span>
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">Ninguna.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">Tus amigos
                    <span class="badge rounded-pill text-bg-light text-dark">{{ $friends->count() }}</span>
                </div>
                <div class="list-group list-group-flush" id="friends-list">
                    @forelse($friends as $f)
                        <a href="{{ route('messages.thread',$f->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $f->name }}</div>
                                <div class="text-muted small">{{ $f->email }}</div>
                            </div>
                            <i class="bi bi-chat-dots"></i>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">Aún no tienes amigos.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const searchInput = document.getElementById('friend-search');
    const resultsBox = document.getElementById('friend-search-results');
    let lastQ = '';
    async function search(q){
        if(!q){ resultsBox.innerHTML=''; return; }
        try {
            const res = await fetch(`/friends/search?q=${encodeURIComponent(q)}`); const j = await res.json();
            if(!j.ok) return; resultsBox.innerHTML='';
            if(!j.results.length){ resultsBox.innerHTML='<div class="list-group-item text-muted">Sin resultados</div>'; return; }
            j.results.forEach(u=>{
                const a = document.createElement('button'); a.type='button'; a.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                a.innerHTML = `<span><strong>${u.name}</strong><br><span class='text-muted small'>${u.email}</span></span><span class='badge text-bg-primary'>+</span>`;
                a.addEventListener('click', async ()=>{
                    try { const r = await fetch(`/friend/${u.id}/request`, {method:'POST', headers:{'X-CSRF-TOKEN':token}}); const jj = await r.json(); if(jj.ok){ window.modalNotification?.('Solicitud enviada', u.name, {template:'success'}); a.remove(); } } catch(_){ }
                });
                resultsBox.appendChild(a);
            });
        } catch(_){}
    }
    searchInput?.addEventListener('input', function(){ const v=this.value.trim(); if(v===lastQ) return; lastQ=v; search(v); });

    // Accept / Reject handlers
    document.getElementById('incoming-list')?.addEventListener('click', async e => {
        const t = e.target; const id = t.getAttribute('data-id'); if(!id) return;
        if(t.classList.contains('btn-accept')){
            try { const r = await fetch(`/friend/request/${id}/accept`,{method:'POST',headers:{'X-CSRF-TOKEN':token}}); const j=await r.json(); if(j.ok){ window.modalNotification?.('Amistad aceptada','Ahora son amigos',{template:'success'}); t.closest('.list-group-item').remove(); } } catch(_){}
        }
        if(t.classList.contains('btn-reject')){
            try { const r = await fetch(`/friend/request/${id}/reject`,{method:'POST',headers:{'X-CSRF-TOKEN':token}}); const j=await r.json(); if(j.ok){ window.modalNotification?.('Solicitud rechazada','Se ha descartado la solicitud',{template:'info'}); t.closest('.list-group-item').remove(); } } catch(_){}
        }
    });

    // Realtime updates: friend request events
    window.addEventListener('rt:friend_request', ev => {
        const d = ev.detail; if(!d || !d.from_name) return;
        // Prepend incoming request card
        const list = document.getElementById('incoming-list'); if(!list) return;
        const div = document.createElement('div'); div.className='list-group-item d-flex justify-content-between align-items-center';
        div.innerHTML = `<div><div class='fw-semibold'>${d.from_name}</div><div class='text-muted small'>(nueva)</div></div><div class='btn-group btn-group-sm'><button class='btn btn-success btn-accept' data-id='${d.id}'>Aceptar</button><button class='btn btn-outline-danger btn-reject' data-id='${d.id}'>Rechazar</button></div>`;
        list.prepend(div);
    });
    window.addEventListener('rt:friend_request_accepted', ev => {
        const d = ev.detail; if(!d || !d.to_name) return;
        // Refresh counters or add friend to list (lightweight approach)
        // We'll call /api/counters to reflect changes
        refreshCounters();
    });

    // Fallback polling: if no realtime event after some seconds, periodically check for new incoming requests
    let lastIncomingIds = Array.from(document.querySelectorAll('#incoming-list [data-id]')).map(el=>parseInt(el.getAttribute('data-id'),10));
    async function pollIncoming(){
        try {
            const r = await fetch('/friend/requests/pending');
            const j = await r.json();
            if(!j.ok) return;
            const currentIds = j.requests.map(r=>r.id);
            // Find new ones not in lastIncomingIds
            const news = j.requests.filter(r => !lastIncomingIds.includes(r.id));
            if(news.length){
                const list = document.getElementById('incoming-list');
                news.forEach(n => {
                    const div = document.createElement('div'); div.className='list-group-item d-flex justify-content-between align-items-center';
                    div.innerHTML = `<div><div class='fw-semibold'>${n.from.name}</div><div class='text-muted small'>(nueva)</div></div><div class='btn-group btn-group-sm'><button class='btn btn-success btn-accept' data-id='${n.id}'>Aceptar</button><button class='btn btn-outline-danger btn-reject' data-id='${n.id}'>Rechazar</button></div>`;
                    list.prepend(div);
                    window.modalNotification?.('Nueva solicitud', n.from.name, {template:'warning'});
                });
                refreshCounters();
            }
            lastIncomingIds = currentIds;
        } catch(_){ }
    }
    // Start fallback only if page visible and user is on friends page
    setInterval(pollIncoming, 15000); // cada 15s

    // Debug logs for realtime connection (opcional)
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        try {
            window.Echo.connector.pusher.connection.bind('connected', function(){ console.debug('[Realtime] connected'); });
            window.Echo.connector.pusher.connection.bind('unavailable', function(){ console.warn('[Realtime] unavailable'); });
            window.Echo.connector.pusher.connection.bind('failed', function(){ console.error('[Realtime] failed'); });
        } catch(_){}
    }

    async function refreshCounters(){
        try { const r=await fetch('/api/counters'); const j=await r.json(); if(!j.ok) return; document.dispatchEvent(new CustomEvent('counters:update',{detail:j})); } catch(_){ }
    }
})();
</script>
@endpush
@extends('layouts.app')

@section('content')
<div class="container py-3" data-thread-user="{{ $partner->id }}">
	<div class="d-flex align-items-center mb-3">
		<a href="{{ route('messages.index') }}" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
		<h1 class="h5 mb-0">Conversación con {{ $partner->name }}</h1>
	</div>
	<div id="messages-box" class="border rounded p-2 mb-3 bg-white" style="height: 400px; overflow-y:auto;">
		@foreach($messages as $m)
			<div class="mb-2 {{ $m->from_id === auth()->id() ? 'text-end' : '' }}">
				<div class="d-inline-block p-2 rounded {{ $m->from_id === auth()->id() ? 'bg-primary text-white' : 'bg-light' }}" style="max-width:70%; white-space:pre-wrap;">{{ $m->body }}</div>
				<div class="small text-muted mt-1">{{ $m->created_at->format('H:i') }} @if($m->from_id === auth()->id() && $m->read_at) <i class="bi bi-check2-all text-primary"></i> @endif</div>
			</div>
		@endforeach
	</div>
	<form id="send-form" class="d-flex gap-2">
		@csrf
		<input type="text" name="body" class="form-control" placeholder="Escribe un mensaje..." autocomplete="off" maxlength="4000" required>
		<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
	</form>
</div>
@endsection

@push('scripts')
<script>
(function(){
	const box = document.getElementById('messages-box');
	if (box) { box.scrollTop = box.scrollHeight; }
	const form = document.getElementById('send-form');
	const threadUser = document.querySelector('[data-thread-user]')?.getAttribute('data-thread-user');
	// track rendered message ids to avoid duplicates coming from realtime + poll
	const renderedMessageIds = new Set();
	function append(msg, opts = {}){
		// msg may be partial in optimistic inserts (no id yet)
		if (msg.id && renderedMessageIds.has(msg.id)) return;
		const isMine = String(msg.from_id) == String({{ auth()->id() }});
		const wrap = document.createElement('div');
		wrap.className = 'mb-2 ' + (isMine ? 'text-end' : '');

		const bubble = document.createElement('div');
		bubble.className = 'd-inline-block p-2 rounded ' + (isMine ? 'bg-primary text-white' : 'bg-light');
		bubble.style.maxWidth = '70%'; bubble.style.whiteSpace = 'pre-wrap';
		bubble.textContent = msg.body || '';

		const meta = document.createElement('div');
		meta.className = 'small text-muted mt-1';
		meta.textContent = msg.created_at ? (new Date(msg.created_at)).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';

		if (msg.id) {
			renderedMessageIds.add(msg.id);
			wrap.dataset.msgId = msg.id;
		}
		if (opts.tempId) wrap.dataset.tempId = opts.tempId;

		wrap.appendChild(bubble);
		wrap.appendChild(meta);
		box.appendChild(wrap);
		// Auto-scroll only when user is near bottom
		try {
			const threshold = 120; // px
			const atBottom = (box.scrollHeight - box.clientHeight - box.scrollTop) < threshold;
			if (atBottom) box.scrollTop = box.scrollHeight;
		} catch(_) { box.scrollTop = box.scrollHeight; }
	}
	form?.addEventListener('submit', async e => {
		e.preventDefault();
		const fd = new FormData(form);
		const body = fd.get('body').trim();
		if(!body) return;
		// optimistic append with a temp id
		const tempId = 't' + Date.now() + Math.floor(Math.random()*1000);
		append({ from_id: {{ auth()->id() }}, body: body, created_at: new Date().toISOString() }, { tempId });
		form.querySelector('[name=body]').value='';
		try {
			const res = await fetch(`{{ route('messages.send', $partner->id) }}`, {method:'POST', headers:{'X-CSRF-TOKEN': fd.get('_token')}, body: fd});
			const data = await res.json();
			if (data.ok) {
				// server returned the message with id: replace temp element if present
				const tempEl = box.querySelector('[data-temp-id="'+tempId+'"]');
				if (tempEl) tempEl.remove();
				append(data.message);
			} else {
				// mark temp as failed (simple visual)
				const tempEl = box.querySelector('[data-temp-id="'+tempId+'"]');
				if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger','text-white');
			}
		} catch(err){
			// console.warn(err);
			const tempEl = box.querySelector('[data-temp-id="'+tempId+'"]');
			if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger','text-white');
		}
	});
	// simple poll for new messages
	setInterval(async ()=>{
		try {
			const res = await fetch(`{{ route('messages.thread', $partner->id) }}?ajax=1`, {headers:{'Accept':'application/json'}});
			if(!res.ok) return; const data = await res.json(); if(!data.ok) return;
			// append only messages not yet rendered
			data.messages.forEach(m => { if (!renderedMessageIds.has(m.id)) append(m); });
		} catch(_){ }
	}, 10000);

	// Realtime: append inbound messages when the thread is open
	window.addEventListener('rt:message', async ev => {
		try {
			const d = ev.detail; if (!d) return;
			// d.from_id should match the partner id for this thread
			const fromId = parseInt(d.from_id, 10);
			const partnerId = parseInt(threadUser, 10);
			if (!partnerId || fromId !== partnerId) return;

			// Build a minimal message object compatible with append()
			const msg = { from_id: d.from_id, body: d.body, created_at: d.created_at };
			append(msg);

			// Hit the thread AJAX endpoint to mark messages as read server-side
			try { await fetch(`{{ route('messages.thread', $partner->id) }}?ajax=1`, {headers:{'Accept':'application/json'}}); } catch(_){}

			// Refresh global counters (unread badges)
			try { refreshCounters(); } catch(_){}
		} catch(_){}
	});
})();
</script>
@endpush
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
	function append(msg){
		const wrap = document.createElement('div');
		wrap.className = 'mb-2 ' + (msg.from_id == {{ auth()->id() }} ? 'text-end' : '');
		wrap.innerHTML = `<div class="d-inline-block p-2 rounded ${msg.from_id == {{ auth()->id() }} ? 'bg-primary text-white' : 'bg-light'}" style="max-width:70%; white-space:pre-wrap;"></div><div class="small text-muted mt-1"></div>`;
		wrap.querySelector('div').textContent = msg.body;
		wrap.querySelectorAll('div')[1].textContent = (new Date(msg.created_at)).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
		box.appendChild(wrap);
		box.scrollTop = box.scrollHeight;
	}
	form?.addEventListener('submit', async e => {
		e.preventDefault();
		const fd = new FormData(form);
		const body = fd.get('body').trim();
		if(!body) return;
		form.querySelector('[name=body]').value='';
		try {
			const res = await fetch(`{{ route('messages.send', $partner->id) }}`, {method:'POST', headers:{'X-CSRF-TOKEN': fd.get('_token')}, body: fd});
			const data = await res.json();
			if (data.ok) append(data.message);
		} catch(err){ console.warn(err); }
	});
	// simple poll for new messages
	setInterval(async ()=>{
		try {
			const res = await fetch(`{{ route('messages.thread', $partner->id) }}?ajax=1`, {headers:{'Accept':'application/json'}});
			if(!res.ok) return; const data = await res.json(); if(!data.ok) return;
			box.innerHTML=''; data.messages.forEach(m=>append(m));
		} catch(_){}
	}, 10000);
})();
</script>
@endpush
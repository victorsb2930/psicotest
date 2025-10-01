@extends('layouts.app')

@section('content')
<div class="container py-3">
	<h1 class="h4 mb-3">Mensajes</h1>
	<div class="row">
		<div class="col-md-4">
			<div class="list-group" id="conversations">
				@forelse($lastMessages as $m)
					@php
						$partner = $m->from_id === auth()->id() ? $m->to : $m->from;
						$unread = $m->to_id === auth()->id() && !$m->read_at;
					@endphp
					<a href="{{ route('messages.thread', $partner->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
						<div>
							<div class="fw-semibold">{{ $partner->name }}</div>
							<div class="text-muted small text-truncate" style="max-width: 180px;">{{ $m->body }}</div>
						</div>
						@if($unread)
							<span class="badge text-bg-primary">Nuevo</span>
						@endif
					</a>
				@empty
					<div class="text-muted small">No tienes conversaciones todavía.</div>
				@endforelse
			</div>
		</div>
		<div class="col-md-8">
			<div class="alert alert-info">Selecciona una conversación para ver los mensajes.</div>
		</div>
	</div>
</div>
@endsection
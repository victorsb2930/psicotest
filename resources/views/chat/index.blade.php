@extends('layout')

@section('title','Chat')
@section('page','chat')

@section('content')
@once
@vite(['resources/css/pages/chat.css'])
@endonce
<div class="py-3">

	<h1 class="h4 mb-3 d-flex align-items-center gap-2">Chat</h1>
	<div class="row">
		<div class="col-lg-4">
			<div class="card mb-3">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<strong>Conversaciones</strong>
						<input id="contacts-search" class="form-control form-control-sm ms-2" placeholder="Buscar"
							style="width:140px">
					</div>
					<div id="contacts-list" class="list-group" style="max-height: 38vh; overflow:auto;">
						@forelse($lastMessages as $m)
						@php
						$partner = $m->from_id === auth()->id() ? $m->to : $m->from;
						$unread = $m->to_id === auth()->id() && !$m->read_at;
						$lastBody = $m->body;
						@endphp
						<button type="button" data-user-id="{{ $partner->id }}" data-user-name="{{ e($partner->name) }}"
							class="list-group-item list-group-item-action contact-item d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-start gap-2">
								<img src="{{ $partner->profile_photo_data_url ?? ($partner->photo ? '/storage/'.ltrim($partner->photo,'/') : Vite::asset('resources/images/default-avatar.png')) }}"
									alt="avatar" width="36" height="36" class="rounded-circle"
									style="object-fit:cover;">
								<div style="min-width:0">
									<div class="fw-semibold text-truncate">{{ $partner->name }}</div>
									<div class="text-muted small text-truncate" style="max-width:160px">{{ $lastBody }}
									</div>
								</div>
							</div>
							<div>
								<span class="presence-dot-small" data-user-id="{{ $partner->id }}" title="No disponible"
									style="width:10px;height:10px;border-radius:50%;background:#6c757d;display:inline-block;margin-top:6px;margin-right:8px"></span>
								@if($unread)<span class="badge text-bg-primary small">Nuevo</span>@endif
							</div>
						</button>
						@empty
						<div class="text-muted small">No tienes conversaciones todavía.</div>
						@endforelse
					</div>
				</div>
			</div>

			{{-- Friends management (integrated) --}}
			<div class="card mb-3">
				<div class="card-header fw-semibold d-flex justify-content-between align-items-center">
					<span>Solicitudes entrantes</span>
					<span class="badge rounded-pill text-bg-light text-dark" id="incoming-count">0</span>
				</div>
				<div class="list-group list-group-flush" id="incoming-list" style="max-height:22vh; overflow:auto;">
				</div>
			</div>
			<div class="card mb-3">
				<div class="card-header fw-semibold d-flex justify-content-between align-items-center">
					<span>Solicitudes enviadas</span>
					<span class="badge rounded-pill text-bg-light text-dark" id="outgoing-count">0</span>
				</div>
				<div class="list-group list-group-flush" id="outgoing-list" style="max-height:18vh; overflow:auto;">
				</div>
			</div>
			<div class="card mb-3">
				<div class="card-header fw-semibold">Buscar usuarios</div>
				<div class="card-body">
					<input id="friend-search" class="form-control mb-2" placeholder="Nombre o email" autocomplete="off">
					<div id="friend-search-results" class="list-group small" style="max-height:200px; overflow:auto;">
					</div>
					<div class="form-text">Solo se muestran usuarios con los que no tienes relación.</div>
				</div>
			</div>
		</div>

		<div class="col-lg-8">
			<div id="chat-panel" class="card">
				<div class="card-body">
					<div id="chat-empty" class="text-center text-muted py-5">Selecciona una conversación para ver los
						mensajes.</div>
					<div id="chat-container" style="display:none;">
						<!-- Chat header: partner name + presence + close -->
						<div class="d-flex justify-content-between align-items-center mb-2">
							<div class="d-flex align-items-center gap-2">
								<span id="chat-partner-presence" class="presence-dot-small" title="No disponible"
									style="width:10px;height:10px;border-radius:50%;background:#6c757d;display:inline-block"></span>
								<strong id="chat-partner-name"></strong>
							</div>
							<div class="btn-group btn-group-sm">
								<button id="chat-video-call" type="button" class="btn btn-outline-primary"
									title="Videollamada"><i class="bi bi-camera-video"></i></button>
								<button id="chat-close" type="button" class="btn btn-outline-secondary"
									title="Cerrar"><i class="bi bi-x-lg"></i></button>
							</div>
						</div>

						<!-- Messages list -->
						<div id="chat-messages" class="border rounded p-2 mb-3 bg-white"
							style="height:55vh; overflow-y:auto;"></div>

						<!-- Send form -->
						<form id="chat-send-form" class="d-flex gap-2">
							@csrf
							<input type="text" name="body" class="form-control" placeholder="Escribe un mensaje..."
								autocomplete="off" maxlength="4000" required>
							<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>
@endsection
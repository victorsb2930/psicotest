@extends('layout')

@section('title','Chat')
@section('page','chat')

@section('content')
@once
@vite(['resources/css/pages/chat.css'])
@endonce
<div class="py-3">

	<h1 class="h4 mb-3 d-flex align-items-center gap-2">Chat
		<button type="button" id="goto-search" class="btn btn-sm btn-primary ms-2">
			<i class="bi bi-person-plus"></i> Añadir contacto
		</button>
	</h1>
	<div class="row">
		<div class="col-lg-4">
			{{-- Contactos (buscar y solicitudes) --}}
			<div class="collapse show" id="contacts-section">
			<div class="card mb-3 contacts-card">
				<div class="card-header p-0">
					<ul class="nav nav-tabs card-header-tabs" id="chat-tabs" role="tablist">
						<li class="nav-item" role="presentation">
							<a class="nav-link active" id="tab-incoming-link" data-bs-toggle="tab" href="#tab-incoming" role="tab" aria-controls="tab-incoming" aria-selected="true">Entrantes <span class="badge rounded-pill text-bg-light text-dark ms-1" id="incoming-count">0</span></a>
						</li>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="tab-outgoing-link" data-bs-toggle="tab" href="#tab-outgoing" role="tab" aria-controls="tab-outgoing" aria-selected="false">Enviadas <span class="badge rounded-pill text-bg-light text-dark ms-1" id="outgoing-count">0</span></a>
						</li>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="tab-search-link" data-bs-toggle="tab" href="#tab-search" role="tab" aria-controls="tab-search" aria-selected="false">Buscar</a>
						</li>
					</ul>
				</div>
				<div class="card-body py-3">
					<div class="tab-content">
						<div class="tab-pane fade show active" id="tab-incoming" role="tabpanel" aria-labelledby="tab-incoming-link">
							<div class="list-group chat-pane" id="incoming-list"></div>
						</div>
						<div class="tab-pane fade" id="tab-outgoing" role="tabpanel" aria-labelledby="tab-outgoing-link">
							<div class="list-group chat-pane" id="outgoing-list"></div>
						</div>
						<div class="tab-pane fade" id="tab-search" role="tabpanel" aria-labelledby="tab-search-link">
							<input id="friend-search" class="form-control mb-2" placeholder="Nombre o email" autocomplete="off">
							<div id="friend-search-results" class="list-group small chat-pane"></div>
							<div class="form-text">Solo se muestran usuarios con los que no tienes relación.</div>
						</div>
					</div>
				</div>
			</div>
			</div>

			{{-- Contactos --}}
			<div class="card mb-3">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<strong>Contactos</strong>
						<input id="contacts-search" class="form-control form-control-sm ms-2" placeholder="Buscar"
							style="width:140px">
					</div>
					<div id="contacts-list" class="list-group">
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
									<div class="text-muted small text-truncate" style="max-width:160px">{{ $lastBody }}</div>
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
								<button id="chat-voice-call" type="button" class="btn btn-outline-primary" title="Llamada de voz"><i class="bi bi-telephone"></i></button>
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
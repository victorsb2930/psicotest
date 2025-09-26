@extends('layout')
@section('title', 'Perfil')
@section('page','profile')
@section('content')
<div class="card">
	<div class="card-header">Mi perfil</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-4 text-center">
				@php
					$user = auth()->user();
					$avatar = Vite::asset('resources/images/p.png');
					if ($user) {
						if (!empty($user->profile_photo_data_url)) $avatar = $user->profile_photo_data_url;
						elseif (!empty($user->photo)) $avatar = '/'.ltrim($user->photo, '/');
					}
				@endphp
				<div id="profile-avatar" style="position:relative; display:inline-block;">
					<img id="profile-avatar-img" src="{{ $avatar }}" alt="avatar" class="rounded-circle" width="140" height="140">
					<span id="profile-presence" style="position:absolute; right:12px; bottom:12px; width:16px; height:16px; border-radius:50%; border:3px solid #fff; background:#28a745; cursor:pointer;"></span>
				</div>
				<div class="mt-3">
					<label class="btn btn-sm btn-outline-secondary" id="btn-change-photo">Subir foto</label>
					<input id="input-photo" type="file" accept="image/*" style="display:none">
				</div>
				<div class="mt-2">
					<small class="text-muted">Estado</small>
					<div class="btn-group mt-1" role="group" aria-label="Presencia">
						<button type="button" class="btn btn-outline-secondary btn-sm presence-btn" data-status="online">Online</button>
						<button type="button" class="btn btn-outline-secondary btn-sm presence-btn" data-status="busy">Ocupado</button>
						<button type="button" class="btn btn-outline-secondary btn-sm presence-btn" data-status="dnd">No molestar</button>
						<button type="button" class="btn btn-outline-secondary btn-sm presence-btn" data-status="away">Ausente</button>
						<button type="button" class="btn btn-outline-secondary btn-sm presence-btn" data-status="offline">No disponible</button>
					</div>
				</div>
			</div>
			<div class="col-md-8">
				<h5>{{ auth()->user()->name }}</h5>
				<p>{{ auth()->user()->email }}</p>
				<hr>
				<h6>Galería</h6>
				<div id="photo-gallery" class="d-flex gap-2 flex-wrap"></div>
			</div>
		</div>
	</div>
</div>

@endsection
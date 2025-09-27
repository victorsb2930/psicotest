@extends('layout')
@section('title', 'Perfil')
@section('page', 'profile')
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

					// Determine initial presence status from server so UI doesn't flash a default value
					$status = ($user && isset($user->status) && $user->status) ? $user->status : 'offline';
					$labels = ['online' => 'Online', 'busy' => 'Ocupado', 'dnd' => 'No molestar', 'away' => 'Ausente', 'offline' => 'No disponible'];
					$colors = ['online' => '#28a745', 'busy' => '#fd7e14', 'dnd' => '#dc3545', 'away' => '#ffc107', 'offline' => '#6c757d'];
					$label = $labels[$status] ?? $status;
					$color = $colors[$status] ?? $colors['offline'];
				@endphp

				<div id="profile-avatar" style="position:relative; display:inline-block;">
					<img id="profile-avatar-img" src="{{ $avatar }}" alt="avatar" class="rounded-circle" width="140" height="140">
					<span id="profile-presence" style="position:absolute; right:12px; bottom:12px; width:16px; height:16px; border-radius:50%; border:3px solid #fff; background:{{ $color }}; cursor:pointer;" aria-hidden="true"></span>
					<span id="profile-presence-desc" class="small text-muted" style="position:absolute; left:calc(100% + 8px); bottom:12px; white-space:nowrap;">{{ $label }}</span>
				</div>
				<div class="mt-3">
					<label class="btn btn-sm btn-outline-secondary" id="btn-change-photo">Subir foto</label>
					<input id="input-photo" type="file" accept="image/*" style="display:none">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
		// ensure modal exists
		if (!document.getElementById('profileImagePreviewModal')) {
				const modalHtml = `
				<div class="modal fade" id="profileImagePreviewModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-lg">
						<div class="modal-content">
							<div class="modal-body text-center p-0">
								<img id="profileImagePreviewModalImg" src="" style="width:100%; height:auto;" alt="preview">
							</div>
						</div>
					</div>
				</div>`;
				document.body.insertAdjacentHTML('beforeend', modalHtml);
		}

		const avatarWrap = document.getElementById('profile-avatar');
		const modalImg = document.getElementById('profileImagePreviewModalImg');
		avatarWrap && avatarWrap.addEventListener('click', function(){
				const src = document.getElementById('profile-avatar-img')?.src || '';
				if(!src) return;
				modalImg.src = src;
				const modalEl = document.getElementById('profileImagePreviewModal');
				if (modalEl) new bootstrap.Modal(modalEl).show();
		});
});
</script>
@endpush
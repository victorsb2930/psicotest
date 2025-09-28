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
	// 2FA modal HTML (for confirming reopen when IP changed)
	if (!document.getElementById('reopen2faModal')) {
		const modal2 = `
		<div class="modal fade" id="reopen2faModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Confirmar reapertura de sesión</h5>
					</div>
					<div class="modal-body">
						<p>Hemos enviado un código a tu correo. Introduce el código de 6 dígitos para confirmar que eres tú.</p>
						<div class="mb-2"><input id="reopen2faCode" class="form-control" placeholder="Código 6 dígitos" maxlength="6" inputmode="numeric"></div>
						<div id="reopen2faError" class="text-danger small" style="display:none"></div>
					</div>
					<div class="modal-footer">
						<button id="reopen2faCancel" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button id="reopen2faSubmit" type="button" class="btn btn-primary">Confirmar</button>
					</div>
				</div>
			</div>
		</div>`;
		document.body.insertAdjacentHTML('beforeend', modal2);
	}

	// Helper to show the 2FA modal and handle submit
	function showReopen2faModal() {
		const modalEl = document.getElementById('reopen2faModal');
		const bs = new bootstrap.Modal(modalEl);
		const input = document.getElementById('reopen2faCode');
		const err = document.getElementById('reopen2faError');
		const submit = document.getElementById('reopen2faSubmit');
		err.style.display = 'none';
		input.value = '';
		submit.onclick = async function() {
			err.style.display = 'none';
			const code = input.value.trim();
			if (!/^[0-9]{6}$/.test(code)) { err.textContent = 'Introduce un código válido de 6 dígitos.'; err.style.display = 'block'; return; }
			submit.disabled = true;
			try {
				const res = await fetch('/profile/heartbeat/confirm', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify({ code }) });
				const j = await res.json();
				if (j.ok) {
					bs.hide();
					// Optionally refresh presence or UI
					location.reload();
				} else {
					err.textContent = j.message || 'Código incorrecto';
					err.style.display = 'block';
				}
			} catch (errNet) {
				err.textContent = 'Error de red. Intenta de nuevo.'; err.style.display = 'block';
			} finally { submit.disabled = false; }
		};
		bs.show();
		setTimeout(() => input.focus(), 250);
	}

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
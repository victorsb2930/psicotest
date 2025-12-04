@extends('layout')
@section('title', 'Perfil')
@section('page', 'profile')
@section('content')
<div class="card">
	@php
		$user = auth()->user();
		$twoFactorPrefHeader = false;
		try {
			if ($user) {
				if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_enabled')) {
					$twoFactorPrefHeader = (bool) ($user->two_factor_enabled ?? false);
				} else {
					$twoFactorPrefHeader = (bool) \Illuminate\Support\Facades\Cache::get('user:'.($user->id ?? '0').':two_factor_enabled', false);
				}
			}
		} catch (\Throwable $_) { $twoFactorPrefHeader = false; }

		$profileFullName = $user ? trim(trim((string) ($user->name ?? '') . ' ' . (string) ($user->lastname ?? ''))) : '';
		if ($user && $profileFullName === '') {
			$profileFullName = (string) ($user->email ?? '');
		}
		$isProfessional = $user && method_exists($user, 'hasRole') ? (bool) $user->hasRole('professional') : false;
		$birthdateInstance = null;
		$birthdateDisplay = 'No especificada';
		$ageDisplay = null;
		if ($user && !empty($user->birthdate)) {
			try {
				$birthdateInstance = \Carbon\Carbon::parse($user->birthdate);
				$birthdateDisplay = $birthdateInstance->format('d/m/Y');
				$ageDisplay = $birthdateInstance->age;
			} catch (\Throwable $_) {
				$birthdateDisplay = (string) $user->birthdate;
			}
		}
		$genderDisplay = $user && $user->gender ? \Illuminate\Support\Str::title($user->gender) : null;
		$locationDisplay = $user && $user->location ? $user->location : null;
		$specialityDisplay = $user && $user->speciality ? $user->speciality : null;
	@endphp
	<div class="card-header d-flex justify-content-between align-items-center">
		<span>Mi perfil</span>
		<div class="dropdown">
			<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="profileSettingsBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de perfil">
				<i class="bi bi-gear"></i>
			</button>
			<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileSettingsBtn">
				<li><a class="dropdown-item" href="#" id="profileEditNamesItem">Editar nombres y apellidos</a></li>
				<li><a class="dropdown-item" href="#" id="profileEditGenderItem">Actualizar género</a></li>
				<li><a class="dropdown-item" href="#" id="profileEditBirthdateItem">Actualizar fecha de nacimiento</a></li>
				<li><a class="dropdown-item" href="#" id="profileEditLocationItem">Actualizar ubicación</a></li>
				@if($isProfessional)
					<li><a class="dropdown-item" href="#" id="profileEditSpecialityItem">Actualizar especialidad</a></li>
				@endif
				<li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="#" id="profileResetPasswordItem">Cambiar contraseña</a></li>
				<li><a class="dropdown-item" href="#" id="profileToggle2faItem" data-two-factor-enabled="{{ $twoFactorPrefHeader ? '1':'0' }}">{{ $twoFactorPrefHeader ? 'Desactivar 2FA' : 'Activar 2FA' }}</a></li>
			</ul>
		</div>
	</div>
	<div class="card-body">
		@if(session('success'))
			<div class="alert alert-success" role="alert">{{ session('success') }}</div>
		@endif
		@if($errors->any())
			<div class="alert alert-danger" role="alert">
				{{ $errors->first() }}
			</div>
		@endif
		<div class="row gy-4 align-items-stretch">
			<div class="col-12 col-lg-4">
				@php
					$user = auth()->user();
					$avatar = Vite::asset('resources/images/default-avatar.png');
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

				<div class="h-100 bg-light-subtle rounded-4 p-4 d-flex flex-column gap-3 text-center text-lg-start align-items-center align-items-lg-start">
					<div class="w-100 d-flex flex-column flex-sm-row flex-lg-column align-items-center align-items-sm-start gap-4">
						<div id="profile-avatar" class="position-relative">
							<img id="profile-avatar-img" src="{{ $avatar }}" alt="avatar" class="rounded-circle shadow-sm" width="140" height="140">
							<span id="profile-presence" style="position:absolute; right:12px; bottom:12px; width:16px; height:16px; border-radius:50%; border:3px solid #fff; background:{{ $color }}; cursor:pointer;" aria-hidden="true"></span>
						</div>
						<div class="d-flex flex-column gap-1 text-center text-sm-start text-lg-start">
							<span class="small text-muted">Estado actual</span>
							<div class="d-flex align-items-center justify-content-center justify-content-sm-start gap-2">
								<span id="profile-presence-desc" class="fw-semibold">{{ $label }}</span>
								<span class="badge rounded-pill bg-secondary-subtle text-secondary">Cambiar</span>
							</div>
						</div>
					</div>
					<div class="w-100 d-flex flex-column flex-sm-row gap-2 justify-content-center justify-content-lg-start">
						<label class="btn btn-outline-secondary w-100 w-sm-auto" id="btn-change-photo">Subir foto</label>
						<input id="input-photo" type="file" accept="image/*" style="display:none">
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-8 d-flex flex-column gap-4">
				<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
					<div>
						<h5 id="profile-display-name" data-profile-field="full_name" class="mb-1">{{ $profileFullName }}</h5>
						<p class="text-muted mb-0">{{ $user->email }}</p>
					</div>
				@php
					$twoFactorPref = false;
					try {
						if (isset($user) && $user) {
							if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_enabled')) {
								$twoFactorPref = (bool) ($user->two_factor_enabled ?? false);
							} else {
								$twoFactorPref = (bool) \Illuminate\Support\Facades\Cache::get('user:'.($user->id ?? '0').':two_factor_enabled', false);
							}
						}
					} catch (\Throwable $_) { $twoFactorPref = false; }
				@endphp
				@php
					// Determine the user's current active plan (best-effort). Fall back to 'No asignado'.
					$currentPlanName = 'No asignado';
					if (
						isset($user) && $user
					) {
						try {
							$sub = $user->subscriptions()->where('status','active')->orderByDesc('starts_at')->first();
							if ($sub && $sub->plan && !empty($sub->plan->name)) {
								$currentPlanName = $sub->plan->name;
							}
						} catch (\Throwable $_) {
							// ignore and leave default
						}
					}
				@endphp
                @if($user->hasRole('user'))
					<p class="mb-0 fw-semibold text-primary">Plan actual: {{ $currentPlanName }}</p>
                @endif
				</div>
				<div class="bg-light rounded-4 p-3 p-md-4">
					<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
						<h6 class="text-uppercase text-muted small mb-0">Datos personales</h6>
						<span class="badge bg-white text-muted border">Actualiza desde el menú</span>
					</div>
					<div class="row row-cols-1 row-cols-sm-2 g-3">
						<div class="col">
							<span class="text-muted text-uppercase small">Nombres</span>
							<p class="mb-0 fw-semibold" data-profile-field="name">{{ $user->name ?? 'No especificado' }}</p>
						</div>
						<div class="col">
							<span class="text-muted text-uppercase small">Apellidos</span>
							<p class="mb-0 fw-semibold" data-profile-field="lastname">{{ $user->lastname ?? 'No especificado' }}</p>
						</div>
						<div class="col">
							<span class="text-muted text-uppercase small">Género</span>
							<p class="mb-0 fw-semibold" data-profile-field="gender">{{ $genderDisplay ?? 'No especificado' }}</p>
						</div>
						<div class="col">
							<span class="text-muted text-uppercase small">Nacimiento</span>
							<div class="d-flex flex-wrap gap-2 align-items-baseline">
								<span class="fw-semibold" data-profile-field="birthdate">{{ $birthdateDisplay }}</span>
								<span class="text-muted small" data-profile-field="age">{{ $ageDisplay ? $ageDisplay.' años' : '' }}</span>
							</div>
						</div>
						<div class="col">
							<span class="text-muted text-uppercase small">Ubicación</span>
							<p class="mb-0 fw-semibold" data-profile-field="location">{{ $locationDisplay ?? 'No especificada' }}</p>
						</div>
						@if($isProfessional)
							<div class="col">
								<span class="text-muted text-uppercase small">Especialidad</span>
								<p class="mb-0 fw-semibold" data-profile-field="speciality">{{ $specialityDisplay ?? 'No especificada' }}</p>
							</div>
						@endif
					</div>
				</div>
				<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
					@include('components.friend_button', ['user' => $user])
				</div>
				<div class="pt-3 border-top">
					<h6 class="mb-3">Galería</h6>
					<div id="photo-gallery" class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start"></div>
				</div>
			</div>
		</div>
		<div id="profileMeta" class="d-none"
			data-name="{{ e($user->name ?? '') }}"
			data-lastname="{{ e($user->lastname ?? '') }}"
			data-gender="{{ e($user->gender ?? '') }}"
			data-birthdate="{{ $birthdateInstance ? $birthdateInstance->toDateString() : '' }}"
			data-location="{{ e($user->location ?? '') }}"
			data-speciality="{{ e($user->speciality ?? '') }}"
			data-email="{{ e($user->email ?? '') }}"
			data-is-professional="{{ $isProfessional ? '1' : '0' }}"
		></div>
	</div>
</div>

@endsection
@push('scripts')
{{-- profile.js creates the reset password modal dynamically to avoid focus/aria-hidden issues. --}}
@endpush

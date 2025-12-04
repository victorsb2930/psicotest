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
		<div class="row">
			<div class="col-md-4 text-center">
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
				<h5 id="profile-display-name" data-profile-field="full_name">{{ $profileFullName }}</h5>
				<p class="text-muted mb-1">{{ $user->email }}</p>
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
				<p class="mb-2"><strong>Tipo de plan:</strong> {{ $currentPlanName }}</p>
				<div class="mt-4">
					<h6 class="text-uppercase text-muted small mb-3">Datos personales</h6>
					<dl class="row gy-2 mb-0">
						<dt class="col-sm-4 text-muted small">Nombres</dt>
						<dd class="col-sm-8" data-profile-field="name">{{ $user->name ?? 'No especificado' }}</dd>
						<dt class="col-sm-4 text-muted small">Apellidos</dt>
						<dd class="col-sm-8" data-profile-field="lastname">{{ $user->lastname ?? 'No especificado' }}</dd>
						<dt class="col-sm-4 text-muted small">Género</dt>
						<dd class="col-sm-8" data-profile-field="gender">{{ $genderDisplay ?? 'No especificado' }}</dd>
						<dt class="col-sm-4 text-muted small">Nacimiento</dt>
						<dd class="col-sm-8">
							<span data-profile-field="birthdate">{{ $birthdateDisplay }}</span>
							<span class="text-muted ms-2" data-profile-field="age">{{ $ageDisplay ? $ageDisplay.' años' : '' }}</span>
						</dd>
						<dt class="col-sm-4 text-muted small">Ubicación</dt>
						<dd class="col-sm-8" data-profile-field="location">{{ $locationDisplay ?? 'No especificada' }}</dd>
						@if($isProfessional)
							<dt class="col-sm-4 text-muted small">Especialidad</dt>
							<dd class="col-sm-8" data-profile-field="speciality">{{ $specialityDisplay ?? 'No especificada' }}</dd>
						@endif
					</dl>
				</div>
				@include('components.friend_button', ['user' => $user])
				<hr>
				<h6>Galería</h6>
				<div id="photo-gallery" class="d-flex gap-2 flex-wrap"></div>
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

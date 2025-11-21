@extends('layouts.app')

@section('content')
<div id="app-content" data-page="admin-appointment-settings" class="container py-4">
	<h1 class="h4 mb-4">Configuración global de citas</h1>
	@if(session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if($errors->any())
		<div class="alert alert-danger">
			<ul class="mb-0">
			@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
			</ul>
		</div>
	@endif
	<form id="appointment-settings-form" method="POST" action="{{ route('admin.appointment.settings.update') }}" class="border rounded p-3 bg-light">
		@csrf
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label">Umbral presencia (%)</label>
				<input type="number" name="presence_threshold_pct" class="form-control" value="{{ old('presence_threshold_pct', $settings->presence_threshold_pct) }}" min="50" max="100" required>
				<small class="text-muted">Porcentaje mínimo para marcar completado.</small>
			</div>
			<div class="col-md-4">
				<label class="form-label">Acceso anticipado (min)</label>
				<input type="number" name="early_access_minutes" class="form-control" value="{{ old('early_access_minutes', $settings->early_access_minutes) }}" min="0" max="120" required>
				<small class="text-muted">Ventana de ingreso antes de la hora.</small>
			</div>
			<div class="col-md-4">
				<label class="form-label">Límite reprogramar (h)</label>
				<input type="number" name="reschedule_deadline_hours" class="form-control" value="{{ old('reschedule_deadline_hours', $settings->reschedule_deadline_hours) }}" min="1" max="168" required>
				<small class="text-muted">Horas antes para permitir reprogramar.</small>
			</div>
			<div class="col-md-4">
				<label class="form-label">Corte reprogramación sin respuesta (h)</label>
				<input type="number" name="unanswered_reprogram_hours" class="form-control" value="{{ old('unanswered_reprogram_hours', $settings->unanswered_reprogram_hours) }}" min="1" max="72" required>
				<small class="text-muted">Horas antes para expirar solicitud pendiente.</small>
			</div>
			<div class="col-md-4">
				<label class="form-label">Intervalo heartbeat (s)</label>
				<input type="number" name="ping_interval_seconds" class="form-control" value="{{ old('ping_interval_seconds', $settings->ping_interval_seconds) }}" min="15" max="300" required>
				<small class="text-muted">Segundos entre pings de presencia.</small>
			</div>
		</div>
		<div class="mt-4 d-flex gap-2">
			<button type="submit" class="btn btn-primary">Guardar cambios</button>
			<button type="button" id="settings-reset-btn" class="btn btn-outline-secondary">Restaurar valores iniciales</button>
		</div>
		<div id="settings-feedback" class="mt-3"></div>
	</form>
</div>
@endsection

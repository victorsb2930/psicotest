@extends('layout')

@section('title','Mis citas')
@section('page','user-appointments')

@section('content')
<div class="container py-4" data-page="user-appointments">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<div class="d-flex align-items-center">
			<h1 class="me-3">Mis citas</h1>
			<button id="newAppointmentBtn" class="btn btn-sm btn-primary">Solicitar una cita</button>
		</div>
		<div class="d-flex align-items-center">
			<div class="input-group input-group-sm" style="width:260px;">
				<input type="text" id="jumpToDate" class="form-control form-control-sm" placeholder="Ir a fecha">
				<button id="jumpToDateBtn" class="btn btn-outline-secondary btn-sm">Ir</button>
			</div>
		</div>
	</div>
	<div id="calendar" style="max-width: 1100px; margin: 0 auto;"></div>
</div>

    

	<meta name="appointments-events-url" content="{{ route('appointments.events') }}">
	<meta name="appointments-store-url" content="{{ route('appointments.store') }}">
	<meta name="appointments-accept-url" content="{{ route('appointments.patient.accept', ['appointment' => 'APPOINTMENT_ID']) }}">
	<meta name="appointments-reject-url" content="{{ route('appointments.patient.reject', ['appointment' => 'APPOINTMENT_ID']) }}">
<script>
	window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);
</script>

@endsection

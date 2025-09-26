@extends('layout')

@section('title','Calendario')
@section('page','professional-calendar')

@section('content')
<div class="container py-4" data-page="professional-calendar">
	<div class="d-flex align-items-center mb-3">
	<h1 class="me-3">Calendario</h1>
	<button class="btn btn-sm btn-primary" id="newAppointmentBtn">Crear nueva cita</button>
	</div>
	<div id="calendar" style="max-width: 1100px; margin: 0 auto;"></div>
</div>

<!-- Provide endpoints and user timezone to the page module via meta tags so the JS module can work with PJAX -->
<meta name="professional-events-url" content="{{ route('professional.calendar.events') }}">
<meta name="professional-patients-url" content="{{ route('professional.calendar.patients') }}">
<meta name="professional-create-url" content="{{ route('professional.calendar.events.store') }}">
<script>
	window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);
</script>

@endsection
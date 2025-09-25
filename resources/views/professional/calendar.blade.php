@extends('layout')

@section('title','Calendario')
@section('page','professional-calendar')

@section('content')
<div class="container py-4">
        <div class="d-flex align-items-center mb-3">
                <h1 class="me-3">Calendario</h1>
                <button class="btn btn-sm btn-primary" id="newAppointmentBtn">Nueva cita</button>
        </div>
        <div id="calendar" style="max-width: 1100px; margin: 0 auto;"></div>
</div>

<!-- New appointment modal -->
<div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="newAppointmentForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Crear cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Paciente</label>
                        <input type="text" id="patientSearch" class="form-control" placeholder="Buscar por nombre o email">
                        <input type="hidden" id="patientId" name="patient_id">
                        <div id="patientResults" class="list-group mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" id="apptTitle" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inicio</label>
                        <input type="datetime-local" name="start" id="apptStart" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fin (opcional)</label>
                        <input type="datetime-local" name="end" id="apptEnd" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" id="apptNotes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Provide endpoints and user timezone to the page module via meta tags so the JS module can work with PJAX -->
<meta name="professional-events-url" content="{{ route('professional.calendar.events') }}">
<meta name="professional-patients-url" content="{{ route('professional.calendar.patients') }}">
<meta name="professional-create-url" content="{{ route('professional.calendar.events.store') }}">
<script>window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);</script>

@endsection

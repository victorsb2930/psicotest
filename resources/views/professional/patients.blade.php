@extends('layout')
@section('title','Pacientes')
@section('page','professional-patients')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="mb-1">Pacientes</h1>
            <p class="text-muted small mb-0">Listado de pacientes con quienes agendaste o atendiste citas.</p>
        </div>
        <a href="{{ route('professionalarea') }}" class="btn btn-sm btn-outline-secondary">&larr; Mi panel</a>
    </div>

    @php $tz = optional(auth()->user())->timezone ?? config('app.timezone'); @endphp

    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">Buscar</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Nombre, email o teléfono">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Ordenar por</label>
            <select name="sort" class="form-select form-select-sm">
                <option value="recent" @selected($filters['sort']==='recent')>Última cita reciente</option>
                <option value="upcoming" @selected($filters['sort']==='upcoming')>Próximas citas</option>
                <option value="sessions" @selected($filters['sort']==='sessions')>Más sesiones</option>
                <option value="name" @selected($filters['sort']==='name')>Nombre A-Z</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Por página</label>
            <select name="per_page" class="form-select form-select-sm">
                @foreach([10,25,50,100] as $size)
                    <option value="{{ $size }}" @selected($filters['per_page']===$size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-grow-1" type="submit">Aplicar</button>
            <a href="{{ route('professional.patients') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    @if($patients->isEmpty())
        <div class="text-muted">Todavía no registras pacientes. Agenda una cita para comenzar.</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th style="width:170px;">Última cita</th>
                    <th style="width:170px;">Próxima cita</th>
                    <th style="width:170px;">Sesiones</th>
                    <th style="width:160px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($patients as $patient)
                    @php
                        $fullName = trim(($patient->patient_name ?? '') . ' ' . ($patient->patient_lastname ?? ''));
                        if ($fullName === '') {
                            $fullName = 'Paciente sin datos';
                        }
                        $lastDate = $patient->last_appointment_at ? $patient->last_appointment_at->timezone($tz)->format('d/m/Y H:i') : '—';
                        $nextDate = $patient->next_appointment_at ? $patient->next_appointment_at->timezone($tz)->format('d/m/Y H:i') : '—';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $fullName }}</div>
                            <div class="small text-muted">{{ $patient->patient_email ?? 'Sin email' }}</div>
                            @if($patient->patient_phone)
                                <div class="small text-muted">{{ $patient->patient_phone }}</div>
                            @endif
                            @if($patient->patient_deleted_at)
                                <span class="badge bg-secondary-subtle text-secondary small mt-1">Cuenta archivada</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $lastDate }}</div>
                            @if($patient->last_appointment_at)
                                <div class="small text-muted">{{ $patient->last_appointment_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $nextDate }}</div>
                            @if($patient->next_appointment_at)
                                <div class="small text-muted">{{ $patient->next_appointment_at->diffForHumans() }}</div>
                            @else
                                <div class="small text-muted">Sin próximas citas</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $patient->total_appointments }} en total</div>
                            <div class="small text-muted">Completadas: {{ $patient->completed_appointments }}</div>
                            <div class="small text-muted">Activas: {{ $patient->active_appointments }}</div>
                            <div class="small text-muted">Próximas: {{ $patient->upcoming_appointments }}</div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-column gap-2">
                                <a href="{{ route('professional.appointments.history', ['patient_id' => $patient->patient_id, 'patient' => $fullName]) }}" class="btn btn-sm btn-outline-primary">Ver citas</a>
                                @if($patient->patient_id)
                                    <a href="/chat?open={{ $patient->patient_id }}" class="btn btn-sm btn-outline-secondary">Abrir chat</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="small text-muted">Mostrando {{ $patients->firstItem() }}–{{ $patients->lastItem() }} de {{ $patients->total() }} pacientes</div>
        <div>{{ $patients->links() }}</div>
    </div>
    @endif
</div>
@endsection

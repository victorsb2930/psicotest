@extends('layout')
@section('title','Mis profesionales')
@section('page','user-professionals')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="mb-1">Mis profesionales</h1>
            <p class="text-muted small mb-0">Personas con quienes ya agendaste o completaste una cita.</p>
        </div>
        <a href="{{ route('userarea') }}" class="btn btn-sm btn-outline-secondary">&larr; Volver</a>
    </div>

    @php
        $tz = optional(auth()->user())->timezone ?? config('app.timezone');
        $returnTo = request()->getRequestUri();
    @endphp

    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">Buscar</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Nombre, email o especialidad">
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
            <a href="{{ route('user.professionals') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    @if($professionals->isEmpty())
        <div class="text-muted">Todavía no registras citas con profesionales.</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Profesional</th>
                    <th style="width:170px;">Última cita</th>
                    <th style="width:170px;">Próxima cita</th>
                    <th style="width:170px;">Sesiones</th>
                    <th style="width:180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($professionals as $pro)
                    @php
                        $fullName = trim(($pro->professional_name ?? '') . ' ' . ($pro->professional_lastname ?? ''));
                        if ($fullName === '') {
                            $fullName = 'Profesional sin datos';
                        }
                        $lastDate = $pro->last_appointment_at ? $pro->last_appointment_at->timezone($tz)->format('d/m/Y H:i') : '—';
                        $nextDate = $pro->next_appointment_at ? $pro->next_appointment_at->timezone($tz)->format('d/m/Y H:i') : '—';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $fullName }}</div>
                            @if($pro->professional_speciality)
                                <div class="small text-muted">{{ $pro->professional_speciality }}</div>
                            @endif
                            <div class="small text-muted">{{ $pro->professional_email ?? 'Sin email' }}</div>
                            @if($pro->professional_phone)
                                <div class="small text-muted">{{ $pro->professional_phone }}</div>
                            @endif
                            @if($pro->professional_deleted_at)
                                <span class="badge bg-secondary-subtle text-secondary small mt-1">Perfil inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $lastDate }}</div>
                            @if($pro->last_appointment_at)
                                <div class="small text-muted">{{ $pro->last_appointment_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $nextDate }}</div>
                            @if($pro->next_appointment_at)
                                <div class="small text-muted">{{ $pro->next_appointment_at->diffForHumans() }}</div>
                            @else
                                <div class="small text-muted">Sin próximas citas</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $pro->total_appointments }} en total</div>
                            <div class="small text-muted">Completadas: {{ $pro->completed_appointments }}</div>
                            <div class="small text-muted">Activas: {{ $pro->active_appointments }}</div>
                            <div class="small text-muted">Próximas: {{ $pro->upcoming_appointments }}</div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-column gap-2">
                                @if($pro->professional_id)
                                    <a href="{{ route('professionals.show', ['id' => $pro->professional_id, 'back' => $returnTo]) }}" class="btn btn-sm btn-outline-primary">Ver perfil</a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success js-user-prof-request"
                                        data-professional-id="{{ $pro->professional_id }}"
                                        data-professional-name="{{ e($fullName) }}"
                                        data-professional-title="{{ e($pro->professional_speciality ?? '') }}"
                                    >Agendar cita</button>
                                    <a href="/chat?open={{ $pro->professional_id }}" class="btn btn-sm btn-outline-secondary">Abrir chat</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="small text-muted">Mostrando {{ $professionals->firstItem() }}–{{ $professionals->lastItem() }} de {{ $professionals->total() }} profesionales</div>
        <div>{{ $professionals->links() }}</div>
    </div>
    @endif
</div>
@endsection

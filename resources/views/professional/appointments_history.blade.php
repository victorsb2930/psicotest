@extends('layout')
@section('title','Historial de citas')
@section('page','professional-appointments-history')
@section('content')
<div class="container py-4">
    <h1 class="mb-3">Historial de citas</h1>
    <div class="mb-2 d-flex gap-2 flex-wrap">
        <a href="{{ route('professional.appointments.history.export',['format'=>'csv'] + request()->query()) }}" class="btn btn-sm btn-outline-success">Exportar CSV</a>
        <a href="{{ route('professional.appointments.history.export',['format'=>'xlsx'] + request()->query()) }}" class="btn btn-sm btn-outline-success">Exportar Excel</a>
    </div>
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('professionalarea') }}" class="btn btn-sm btn-outline-secondary">&larr; Volver</a>
        <form method="get" class="row g-2 align-items-end" style="flex:1;">
            <div class="col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- todos --</option>
                    @foreach($allowedStatuses as $st)
                        <option value="{{ $st }}" @selected($filters['status']===$st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Paciente</label>
                <input name="patient" value="{{ $filters['patient'] }}" class="form-control form-control-sm" placeholder="Nombre o email">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Orden</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="start_desc" @selected($filters['sort']==='start_desc')>Recientes primero</option>
                    <option value="start_asc" @selected($filters['sort']==='start_asc')>Antiguas primero</option>
                    <option value="status" @selected($filters['sort']==='status')>Por estado</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1" type="submit">Filtrar</button>
                <a href="{{ route('professional.appointments.history') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    @php
        function renderStars($score){
            if(!$score) return '<span class="text-muted small">(sin)</span>';
            $s = (int) $score; $out='';
            for($i=1;$i<=5;$i++){ $filled = $i <= $s; $out .= '<i class="bi '.($filled?'bi-star-fill text-warning':'bi-star text-secondary').'" style="font-size:0.9rem"></i>'; }
            return $out . ' <span class="small text-muted">'.number_format($score,1).'★</span>';
        }
    @endphp

    @if($appointments->count() === 0)
        <div class="text-muted">No hay citas que coincidan con los filtros.</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:140px;">Fecha</th>
                    <th>Paciente</th>
                    <th>Título / Notas</th>
                    <th style="width:110px;">Estado</th>
                    <th style="width:90px;">Duración</th>
                    <th style="width:140px;">Calificación</th>
                    <th style="width:110px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($appointments as $a)
                    @php
                        $start = $a->start ? $a->start->format('d/m/Y H:i') : null;
                        $end = $a->end ? $a->end->format('H:i') : null;
                        $dur = ($a->start && $a->end) ? $a->end->diffInMinutes($a->start).' min' : '—';
                        $ratingScore = $a->rating?->score;
                        $statusLabel = strtoupper($a->status);
                        $badgeClass = match($a->status){
                            'completed' => 'bg-success',
                            'no_show','skipped' => 'bg-warning text-dark',
                            'rejected','cancelled','canceled' => 'bg-danger',
                            'in_progress' => 'bg-info text-dark',
                            'accepted' => 'bg-primary',
                            default => 'bg-secondary'
                        };
                        $title = $a->title ?? ($a->patient?->name ? 'Cita con '.$a->patient->name : 'Cita');
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ $start }}@if($end) – {{ $end }}@endif</td>
                        <td class="text-nowrap">{{ $a->patient?->name ?? '—' }}<div class="small text-muted">{{ $a->patient?->email }}</div></td>
                        <td>
                            <div class="fw-semibold">{{ $title }}</div>
                            @if($a->notes)
                                <div class="small text-muted" style="max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $a->notes }}</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $badgeClass }} small w-100">{{ $statusLabel }}</span></td>
                        <td>{{ $dur }}</td>
                        <td>{!! renderStars($ratingScore) !!}</td>
                        <td class="text-end">
                            <a href="{{ route('professional.calendar') }}?open={{ $a->id }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            @if($a->patient)
                                <a href="/chat?open={{ $a->patient->id }}" class="btn btn-sm btn-outline-secondary ms-1">Chat</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="small text-muted">Mostrando {{ $appointments->firstItem() }}–{{ $appointments->lastItem() }} de {{ $appointments->total() }} citas</div>
        <div>{{ $appointments->links() }}</div>
    </div>
    @endif
</div>
@endsection
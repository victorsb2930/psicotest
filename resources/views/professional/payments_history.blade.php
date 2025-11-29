@extends('layout')
@section('title','Historial de pagos')
@section('page','professional-payments')
@section('content')
<div class="container py-4">
    <h1 class="mb-3">Historial de pagos</h1>
    <div class="mb-3 d-flex gap-2">
        <a href="{{ route('professional.payments.history.export',['format'=>'csv'] + request()->query()) }}" class="btn btn-sm btn-outline-success">Exportar CSV</a>
        <a href="{{ route('professional.payments.history.export',['format'=>'xlsx'] + request()->query()) }}" class="btn btn-sm btn-outline-success">Exportar Excel</a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">-- todos --</option>
                <option value="succeeded" @selected(($filters['status'] ?? '')==='succeeded')>succeeded</option>
                <option value="pending" @selected(($filters['status'] ?? '')==='pending')>pending</option>
                <option value="failed" @selected(($filters['status'] ?? '')==='failed')>failed</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2 align-items-end">
            <button class="btn btn-sm btn-primary" type="submit">Filtrar</button>
            <a href="{{ route('professional.payments.history') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    @if($payments->count() === 0)
        <div class="text-muted">No hay pagos que coincidan con los filtros.</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Payer</th>
                    <th>Referencia</th>
                    <th style="width:140px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                    @php
                        $amount = number_format(($p->amount_cents / 100), 2);
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ optional($p->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $amount }} {{ $p->currency }}</td>
                        <td class="text-nowrap">{{ $p->status }}</td>
                        <td>{{ $p->user?->name ?? '—' }}<div class="small text-muted">{{ $p->user?->email }}</div></td>
                        <td>{{ $p->provider }} @if($p->provider_charge_id) / {{ $p->provider_charge_id }}@endif</td>
                        <td class="text-end">
                            @if($p->meta['appointment_id'] ?? false)
                                <a href="{{ route('professional.appointments.history') }}?open={{ $p->meta['appointment_id'] }}" class="btn btn-sm btn-outline-primary">Ver cita</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="small text-muted">Mostrando {{ $payments->firstItem() }}–{{ $payments->lastItem() }} de {{ $payments->total() }} pagos</div>
        <div>{{ $payments->links() }}</div>
    </div>
    @endif
</div>
@endsection

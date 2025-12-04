@extends('layout')
@section('title','Historial de pagos')
@section('page','professional-payments')
@section('content')
<div class="container py-4">
    <h1 class="mb-3">Historial de pagos</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $pendingAmount = number_format((($payoutStats['pending_cents'] ?? 0) / 100), 2);
        $confirmedAmount = number_format((($payoutStats['confirmed_cents'] ?? 0) / 100), 2);
        $failedAmount = number_format((($payoutStats['failed_cents'] ?? 0) / 100), 2);
        $maxPayout = number_format((($maxPayoutCents ?? 0) / 100), 2);
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">Resumen de mis payouts</h5>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 bg-warning-subtle h-100">
                                <small class="text-muted d-block">Por confirmar</small>
                                <div class="fs-4 fw-bold text-warning">${{ $pendingAmount }}</div>
                                <div class="small text-muted">Pagos pendientes de tu confirmación</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 bg-success-subtle h-100">
                                <small class="text-muted d-block">Confirmados</small>
                                <div class="fs-4 fw-bold text-success">${{ $confirmedAmount }}</div>
                                <div class="small text-muted">Total recibido históricamente</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 bg-light h-100">
                                <small class="text-muted d-block">Fallidos / cancelados</small>
                                <div class="fs-4 fw-bold text-muted">${{ $failedAmount }}</div>
                                <div class="small text-muted">Montos anulados por la plataforma</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 small text-muted">Cada payout pasa a <strong>succeeded</strong> cuando confirmas su recepción o soporte lo marca como pagado.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Enviar dinero a mi tarjeta</h5>
                    <p class="small text-muted">Simula el desembolso hacia una tarjeta personal. Crearemos un payout <em>pending</em> y podrás confirmarlo cuando el dinero llegue.</p>
                    <form action="{{ route('professional.payments.card_transfer') }}" method="POST" class="d-grid gap-2">
                        @csrf
                        <div>
                            <label class="form-label small">Monto (máx. ${{ $maxPayout }})</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">USD</span>
                                <input type="number" name="amount" step="0.01" min="0" class="form-control" placeholder="150.00" value="{{ old('amount') }}" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small">Nombre en la tarjeta</label>
                            <input type="text" name="card_holder" class="form-control form-control-sm" maxlength="120" value="{{ old('card_holder', trim(auth()->user()->name.' '.auth()->user()->lastname)) }}" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small">Últimos 4 dígitos</label>
                                <input type="text" name="card_last4" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" class="form-control form-control-sm" value="{{ old('card_last4') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Marca</label>
                                <input type="text" name="card_brand" class="form-control form-control-sm" maxlength="40" placeholder="Visa" value="{{ old('card_brand') }}">
                            </div>
                        </div>
                        <div>
                            <label class="form-label small">Notas internas (opcional)</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm" maxlength="300">{{ old('notes') }}</textarea>
                        </div>
                        <button class="btn btn-primary btn-sm mt-1" type="submit">Crear payout simulado</button>
                    </form>
                    <div class="mt-3 small text-muted">El registro aparecerá como proveedor <strong>card_simulator</strong>. Cuando recibas el dinero, usa el botón “Confirmar recepción”.</div>
                </div>
            </div>
        </div>
    </div>

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
                    <th>Destino</th>
                    <th>Referencia</th>
                    <th style="width:200px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                    @php
                        $amount = number_format(($p->amount_cents / 100), 2);
                        $destination = '—';
                        if (($p->meta['destination'] ?? null) === 'card') {
                            $brand = strtoupper($p->meta['card_brand'] ?? 'CARD');
                            $last4 = $p->meta['card_last4'] ?? '0000';
                            $destination = "Tarjeta {$brand} ••••{$last4}";
                        } elseif ($p->provider === 'manual') {
                            $destination = 'Transferencia manual';
                        } elseif ($p->recipient_user_id === optional($p->user)->id) {
                            $destination = 'Saldo interno';
                        }
                        $statusClass = match($p->status) {
                            'succeeded' => 'bg-success-subtle text-success fw-semibold',
                            'failed' => 'bg-danger-subtle text-danger fw-semibold',
                            default => 'bg-warning-subtle text-warning fw-semibold',
                        };
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ optional($p->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $amount }} {{ $p->currency }}</td>
                        <td class="text-nowrap"><span class="badge {{ $statusClass }} text-uppercase">{{ $p->status }}</span></td>
                        <td>{{ $p->user?->name ?? '—' }}<div class="small text-muted">{{ $p->user?->email }}</div></td>
                        <td>{{ $destination }}</td>
                        <td>{{ $p->provider }} @if($p->provider_charge_id) / {{ $p->provider_charge_id }}@endif</td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                @if($p->meta['appointment_id'] ?? false)
                                    <a href="{{ route('professional.appointments.history') }}?open={{ $p->meta['appointment_id'] }}" class="btn btn-sm btn-outline-primary">Ver cita</a>
                                @endif
                                @if($p->type === 'payout' && $p->status === 'pending')
                                    <form
                                        action="{{ route('professional.payments.confirm', $p) }}"
                                        method="POST"
                                        data-payout-confirm="true"
                                        data-confirm-id="{{ $p->id }}"
                                        data-confirm-amount="{{ $amount }}"
                                        data-confirm-currency="{{ $p->currency }}"
                                        data-confirm-destination="{{ e($destination) }}"
                                        data-confirm-created="{{ optional($p->created_at)->format('d/m/Y H:i') }}"
                                        data-confirm-provider="{{ $p->provider }}"
                                        @if($p->provider_charge_id)
                                            data-confirm-reference="{{ $p->provider_charge_id }}"
                                        @endif
                                    >
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success w-100">Confirmar recepción</button>
                                    </form>
                                @endif
                            </div>
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

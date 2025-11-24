@extends('layouts.app')

@section('page', 'admin-payments')

@section('content')
<div class="container py-4">
    <h1>Gestión de Pagos</h1>
    <p class="text-muted">Listado de pagos recibidos por la plataforma y pagos/payouts a profesionales.</p>

    <div class="mb-3">
        <div class="d-flex align-items-center gap-3">
            <div>
                <small class="text-muted">Capital de la plataforma</small>
                <div><strong>${{ $platform_balance ?? '0.00' }}</strong></div>
            </div>
            <div class="text-muted small">
                <div>Recibido por ventas: ${{ $platform_received ?? '0.00' }}</div>
                <div>Pagos a profesionales: ${{ $platform_payouts ?? '0.00' }}</div>
            </div>
        </div>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <select name="type" class="form-select">
                <option value="">Todos los tipos</option>
                <option value="sale" {{ request('type')=='sale' ? 'selected' : '' }}>Venta</option>
                <option value="payout" {{ request('type')=='payout' ? 'selected' : '' }}>Pago a profesional</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="succeeded" {{ request('status')=='succeeded' ? 'selected' : '' }}>Sucedido</option>
                <option value="pending" {{ request('status')=='pending' ? 'selected' : '' }}>Pendiente</option>
                <option value="failed" {{ request('status')=='failed' ? 'selected' : '' }}>Fallido</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <div class="table-responsive">
        <div class="d-flex justify-content-end mb-2">
            <button id="btn-create-payout" class="btn btn-sm btn-success">Crear payout a profesional</button>
        </div>
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Comprador</th>
                    <th>Receptor</th>
                    <th>Tipo</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Proveedor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td>{{ optional($p->created_at)->toDateTimeString() }}</td>
                    <td>{{ optional($p->user)->name ?? '—' }}</td>
                    <td>{{ optional($p->recipient)->name ?? 'Plataforma' }}</td>
                    <td>{{ $p->type ?? 'sale' }}</td>
                    <td>{{ number_format(($p->amount_cents ?? 0)/100, 2) }} {{ $p->currency }}</td>
                    <td>{{ $p->status }}</td>
                    <td>{{ $p->provider }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $payments->withQueryString()->links() }}</div>
</div>
@endsection

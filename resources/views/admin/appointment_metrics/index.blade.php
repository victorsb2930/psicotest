@extends('layouts.app')

@section('content')
<div class="container py-4" data-page="admin-appointment-metrics" data-metrics-endpoint="{{ route('admin.appointment.metrics.json',["limit"=>60]) }}">
  <h1 class="h4 mb-3">Métricas de Citas (últimos 60 días)</h1>
  <div class="mb-4">
    <div class="small text-muted">Visualizaciones</div>
    <div class="row g-3 mt-1">
      <div class="col-12 col-xl-6">
        <div class="p-3 border rounded bg-white position-relative" style="min-height:320px">
          <h6 class="mb-2">Estados de Citas</h6>
          <canvas id="chart-status" height="220" aria-label="Estados citas"></canvas>
          <div class="small text-muted mt-2" id="chart-status-summary"></div>
        </div>
      </div>
      <div class="col-12 col-xl-6">
        <div class="p-3 border rounded bg-white position-relative" style="min-height:320px">
          <h6 class="mb-2">Calidad Promedio</h6>
          <canvas id="chart-quality" height="220" aria-label="Calidad"></canvas>
          <div class="small text-muted mt-2" id="chart-quality-summary"></div>
        </div>
      </div>
      <div class="col-12">
        <div class="p-3 border rounded bg-white position-relative" style="min-height:320px">
          <h6 class="mb-2">Red / Retries</h6>
          <canvas id="chart-network" height="220" aria-label="Red"></canvas>
          <div class="small text-muted mt-2" id="chart-network-summary"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">Citas totales</div>
        <div class="fw-semibold">{{ $summary['total_appointments'] }}</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">% Completadas</div>
        <div class="fw-semibold">{{ $summary['completed_pct'] ? number_format($summary['completed_pct'],1) : '—' }}%</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">% No-Show</div>
        <div class="fw-semibold">{{ $summary['no_show_pct'] ? number_format($summary['no_show_pct'],1) : '—' }}%</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">% Skipped</div>
        <div class="fw-semibold">{{ $summary['skipped_pct'] ? number_format($summary['skipped_pct'],1) : '—' }}%</div>
      </div>
    </div>
  </div>
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">Avg Bitrate</div>
        <div class="fw-semibold">{{ $summary['avg_bitrate_kbps'] ? number_format($summary['avg_bitrate_kbps'],1) : '—' }} kbps</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">Avg Pérdida</div>
        <div class="fw-semibold">{{ $summary['avg_loss_pct'] ? number_format($summary['avg_loss_pct'],2) : '—' }}%</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">Avg RTT</div>
        <div class="fw-semibold">{{ $summary['avg_rtt_ms'] ? number_format($summary['avg_rtt_ms'],1) : '—' }} ms</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="p-3 border rounded bg-light">
        <div class="small text-muted">Avg Retries</div>
        <div class="fw-semibold">{{ $summary['avg_retries'] ? number_format($summary['avg_retries'],2) : '—' }}</div>
      </div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>Fecha</th>
          <th>Citas</th>
          <th>Completadas</th>
          <th>No-Show</th>
          <th>Skipped</th>
          <th>Sesiones c/ métricas</th>
          <th>Avg Bitrate</th>
          <th>Avg Pérdida</th>
          <th>Avg RTT</th>
          <th>Avg Retries</th>
          <th>Degraded Seq Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $r)
          <tr>
            <td>{{ $r->day }}</td>
            <td>{{ $r->total_appointments }}</td>
            <td>{{ $r->completed_count }}</td>
            <td class="text-danger">{{ $r->no_show_count }}</td>
            <td class="text-warning">{{ $r->skipped_count }}</td>
            <td>{{ $r->metrics_sessions }}</td>
            <td>{{ $r->avg_bitrate_kbps !== null ? number_format($r->avg_bitrate_kbps,1) : '—' }}</td>
            <td>{{ $r->avg_loss_pct !== null ? number_format($r->avg_loss_pct,2) : '—' }}%</td>
            <td>{{ $r->avg_rtt_ms !== null ? number_format($r->avg_rtt_ms,1) : '—' }} ms</td>
            <td>{{ $r->avg_retries !== null ? number_format($r->avg_retries,2) : '—' }}</td>
            <td>{{ $r->degraded_sequences_total }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@push('scripts')
{{-- Gestión movida a módulo JS (resources/js/pages/admin.appointment.metrics.js) --}}
@endpush
@endsection

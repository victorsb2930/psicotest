@extends('layout')
@section('title','Mis Calificaciones')
@section('page','professional-ratings')
@section('content')
@php $user = Auth::user(); @endphp
<div class="container py-4">
  <h3 class="mb-3">Mis Calificaciones</h3>
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div class="small text-muted">Promedio</div>
        <div class="display-6">{{ number_format($avg,2) }}</div>
        <div class="text-warning">
          @for($i=1;$i<=5;$i++)
            <i class="bi {{ $i <= round($avg) ? 'bi-star-fill':'bi-star' }}"></i>
          @endfor
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div class="small text-muted">Total</div>
        <div class="h3 mb-0">{{ $total }}</div>
        <div class="small text-muted">Calificaciones</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div class="small text-muted">% 4+ estrellas</div>
        <div class="h3 mb-0">{{ $pctHigh }}%</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3">
        <div class="small text-muted">Distribución</div>
        @foreach([5,4,3,2,1] as $s)
          @php $count = $breakdown[$s] ?? 0; $pct = $total>0? round(($count/$total)*100):0; @endphp
          <div class="d-flex align-items-center mb-1">
            <div style="width:35px" class="text-end small">{{ $s }}★</div>
            <div class="progress flex-grow-1 mx-2" style="height:6px;">
              <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pct }}%" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="small" style="width:40px">{{ $count }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <form id="ratings-filters" class="card p-3 mb-4">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small">Mínimo estrellas</label>
        <select class="form-select form-select-sm" name="min">
          <option value="">Todas</option>
          @for($i=5;$i>=1;$i--) <option value="{{ $i }}">{{ $i }}+</option> @endfor
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Con comentario</label>
        <select class="form-select form-select-sm" name="has_comment">
          <option value="">--</option>
          <option value="1">Sí</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Desde</label>
        <input type="date" class="form-control form-control-sm" name="from">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Hasta</label>
        <input type="date" class="form-control form-control-sm" name="to">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Buscar texto</label>
        <input type="text" class="form-control form-control-sm" name="q" placeholder="palabra">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
        <button type="reset" class="btn btn-outline-secondary btn-sm" id="reset-filters">Reset</button>
      </div>
    </div>
  </form>

  <div class="card p-3">
    <h5 class="mb-3">Listado</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle" id="ratings-table">
        <thead>
          <tr class="small">
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Estrellas</th>
            <th style="width:30%">Comentario</th>
            <th>Visible</th>
            <th style="width:25%">Respuesta</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        @foreach($ratings as $r)
          @php $alias = 'Usuario #'.str_pad($r->patient_id,4,'0',STR_PAD_LEFT); @endphp
          <tr data-id="{{ $r->id }}">
            <td class="small text-muted">{{ optional($r->created_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $alias }}</td>
            <td>
              <span class="text-warning">
                @for($i=1;$i<=5;$i++)<i class="bi {{ $i <= $r->rating ? 'bi-star-fill':'bi-star' }}"></i>@endfor
              </span>
            </td>
            <td class="small">{{ $r->is_public ? ($r->comment ?: '—') : '—' }}</td>
            <td>
              <div class="form-check form-switch m-0">
                <input class="form-check-input visibility-toggle" type="checkbox" {{ $r->is_public ? 'checked':'' }}>
              </div>
            </td>
            <td>
              <textarea class="form-control form-control-sm response-text" rows="2" maxlength="2000" placeholder="Respuesta opcional">{{ $r->response_text }}</textarea>
              <div class="small text-muted mt-1 response-meta">@if($r->responded_at) Respondido: {{ $r->responded_at->diffForHumans() }} @endif</div>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-primary save-response" disabled>Guardar</button>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <div>
      {{ $ratings->links() }}
    </div>
  </div>
</div>
@endsection
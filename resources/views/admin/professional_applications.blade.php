@extends('layout')
@section('title','Solicitudes Profesionales')
@section('page','admin-professional-apps')
@section('content')
<div class="container py-4">

  <h1 class="mb-3">Solicitudes de Profesionales</h1>
  @include('admin._flash')

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="text-muted small">Usa el filtro para buscar y revisar.</div>
  </div>

  <form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
      <label class="form-label mb-0">Estado</label>
      <select name="status" class="form-select">
        <option value="">Todos</option>
        @foreach(['pending'=>'Pendiente','approved'=>'Aprobado','rejected'=>'Rechazado'] as $k=>$v)
          <option value="{{$k}}" @selected(($status??'')===$k)>{{$v}}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label mb-0">Buscar</label>
      <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Nombre o email">
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th><th>Usuario</th><th>Documentos</th><th>Estado</th><th>Revisión</th><th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($apps as $a)
          <tr>
            <td>{{$a->id}}</td>
            <td>
              <div class="fw-semibold">{{$a->user?->name}}</div>
              <div class="text-muted small">{{$a->user?->email}}</div>
            </td>
            <td>
              @if($a->titulo_path)
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'titulo']) }}">Título</a>
              @endif
              @if($a->cedula_path)
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'cedula']) }}">Cédula</a>
              @endif
            </td>
            <td>
        @php
          switch($a->status) {
            case 'pending':
              $badge = 'bg-warning text-dark';
              break;
            case 'approved':
              $badge = 'bg-success';
              break;
            case 'rejected':
              $badge = 'bg-danger';
              break;
            default:
              $badge = '';
          }
        @endphp
              <span class="badge {{$badge}}">{{$a->status}}</span>
            </td>
            <td>
              @if($a->reviewed_at)
                <div class="small">Por: {{$a->reviewer?->name}}</div>
                <div class="text-muted small">{{$a->reviewed_at}}</div>
                @if($a->notes)<div class="small">Nota: {{ $a->notes }}</div>@endif
              @else
                <span class="text-muted small">Sin revisar</span>
              @endif
            </td>
            <td class="text-end">
              @if($a->status==='pending')
                <form method="POST" action="{{ route('admin.profapps.approve', $a) }}" class="d-inline">@csrf
                  <button class="btn btn-sm btn-success">Aprobar</button>
                </form>
                <form method="POST" action="{{ route('admin.profapps.reject', $a) }}" class="d-inline js-reject-form">@csrf
                  <input type="hidden" name="notes" value="" />
                  <button type="button" class="btn btn-sm btn-outline-danger js-open-reject">Rechazar</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $apps->links() }}</div>
  </div>
</div>

@push('scripts')
<script>
  // Página: admin-professional-apps -> handled by resources/js/pages/admin.profapps.js
</script>
@endpush
@endsection

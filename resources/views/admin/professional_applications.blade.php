@extends('layout')
@section('title','Solicitudes Profesionales')
@section('page', 'admin-professional-apps')
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
            <th>ID</th><th>Usuario</th><th>Documentos</th><th>Estado</th><th>Revisión</th><th>Motivo</th><th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($apps as $a)
            <tr data-app-id="{{$a->id}}" class="app-row" data-status="{{$a->status}}"
              data-docs-required="{{ implode(',', collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($a->{$d.'_path'}))->all()) }}"
              data-docs-viewed="{{ implode(',', collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($a->{$d.'_viewed_at'}))->all()) }}">
            <td>{{$a->id}}</td>
            <td>
              <div class="fw-semibold">{{$a->user?->name}}</div>
              <div class="text-muted small">{{$a->user?->email}}</div>
            </td>
            <td class="small">
              <div class="d-flex flex-wrap gap-1 doc-buttons">
                @if($a->titulo_path)
                  <a class="btn btn-sm btn-outline-secondary js-doc" data-doc="titulo" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'titulo']) }}">Título <span class="doc-state" data-state="{{ $a->titulo_viewed_at? 'v':'' }}">@if($a->titulo_viewed_at)<i class="bi bi-check-circle text-success"></i>@endif</span></a>
                @endif
                @if($a->cedula_path)
                  <a class="btn btn-sm btn-outline-secondary js-doc" data-doc="cedula" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'cedula']) }}">Cédula <span class="doc-state" data-state="{{ $a->cedula_viewed_at? 'v':'' }}">@if($a->cedula_viewed_at)<i class="bi bi-check-circle text-success"></i>@endif</span></a>
                @endif
                @if($a->cv_path)
                  <a class="btn btn-sm btn-outline-secondary js-doc" data-doc="cv" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'cv']) }}">CV <span class="doc-state" data-state="{{ $a->cv_viewed_at? 'v':'' }}">@if($a->cv_viewed_at)<i class="bi bi-check-circle text-success"></i>@endif</span></a>
                @endif
                @if($a->exequatur_path)
                  <a class="btn btn-sm btn-outline-secondary js-doc" data-doc="exequatur" target="_blank" href="{{ route('admin.profapps.file', [$a,'field'=>'exequatur']) }}">Exequátur <span class="doc-state" data-state="{{ $a->exequatur_viewed_at? 'v':'' }}">@if($a->exequatur_viewed_at)<i class="bi bi-check-circle text-success"></i>@endif</span></a>
                @endif
              </div>
              <div class="small mt-1 doc-progress text-muted">
                @php $req = collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($a->{$d.'_path'}))->count();
                     $seen = collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($a->{$d.'_viewed_at'}))->count(); @endphp
                Revisados: <span class="doc-seen-count">{{$seen}}</span>/{{$req}}
              </div>
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
                <div class="text-muted small">{{ \Carbon\Carbon::parse($a->reviewed_at)->format('d/m/Y H:i')}}</div>
              @else
                <span class="text-muted small">Sin revisar</span>
              @endif
            </td>
            <td class="small">
              @if($a->status==='rejected')
                @if($a->notes)
                  <div>"{{$a->notes}}"</div>
                @else
                  <span class="text-muted">(sin motivo)</span>
                @endif
              @else
                @if($a->notes)
                  <span class="text-muted">{{$a->notes}}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              @endif
            </td>
            <td class="text-end">
              @if($a->status==='pending')
                <form method="POST" action="{{ route('admin.profapps.approve', $a) }}" class="d-inline app-action-form app-approve" data-action="approve">@csrf
                  <button class="btn btn-sm btn-success" disabled>Aprobar</button>
                </form>
                <form method="POST" action="{{ route('admin.profapps.reject', $a) }}" class="d-inline js-reject-form app-action-form app-reject" data-action="reject">@csrf
                  <input type="hidden" name="notes" value="" />
                  <button type="button" class="btn btn-sm btn-outline-danger js-open-reject" disabled>Rechazar</button>
                </form>
                <div class="small text-muted mt-1 action-hint">Revisa todos los documentos para habilitar acciones.</div>
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
@endsection

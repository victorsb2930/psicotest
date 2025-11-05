@extends('layouts.app')

@section('content')
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Menú (Items)</h1>
    <a class="btn btn-primary" href="{{ route('admin.menuitems.create') }}"><i class="bi bi-plus-lg me-1"></i>Nuevo</a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" class="form-control" name="q" value="{{ $search ?? '' }}" placeholder="label / ruta / url">
    </div>
    <div class="col-md-3">
      <label class="form-label">Sección</label>
      <select name="section" class="form-select">
        <option value="">Todas</option>
        @foreach(($sections ?? collect()) as $sec)
          <option value="{{ $sec }}" @selected(($section ?? '')===$sec)>{{ $sec }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100" type="submit">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Sección</th>
          <th>Orden</th>
          <th>Label</th>
          <th>Ruta</th>
          <th>URL</th>
          <th>Permiso</th>
          <th>Enabled</th>
          <th>Roles</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr>
            <td><span class="badge text-bg-light text-dark">{{ $it->section }}</span></td>
            <td>{{ $it->sort_order }}</td>
            <td>
              @if($it->icon_class)<i class="{{ $it->icon_class }} me-1"></i>@endif
              {{ $it->label }}
            </td>
            <td><code>{{ $it->route_name }}</code></td>
            <td>{{ $it->url }}</td>
            <td><code>{{ $it->permission }}</code></td>
            <td>
              <form action="{{ route('admin.menuitems.toggle', $it) }}" method="post">
                @csrf
                <button class="btn btn-sm {{ $it->enabled ? 'btn-success' : 'btn-outline-secondary' }}" type="submit">{{ $it->enabled ? 'ON' : 'OFF' }}</button>
              </form>
            </td>
            <td>
              @php $roleNames = $it->roles()->pluck('name')->toArray(); @endphp
              @forelse($roleNames as $rn)
                <span class="badge text-bg-secondary">{{ $rn }}</span>
              @empty
                <span class="text-muted">Todos</span>
              @endforelse
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.menuitems.edit', $it) }}">Editar</a>
              <form action="{{ route('admin.menuitems.destroy', $it) }}" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar elemento?');">
                @csrf @method('delete')
                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted">Sin elementos</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>
    {{ $items->links() }}
  </div>
</div>
@endsection

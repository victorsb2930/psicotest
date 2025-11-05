@php
  $isEdit = isset($item) && $item && $item->exists;
  $action = $isEdit ? route('admin.menuitems.update', $item) : route('admin.menuitems.store');
  $method = $isEdit ? 'put' : 'post';
  $selected = $selectedRoles ?? ($item->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() ?? []);
@endphp

<form action="{{ $action }}" method="post" class="card">
  @csrf
  @if($isEdit) @method('put') @endif
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Label</label>
        <input type="text" name="label" class="form-control" required value="{{ old('label', $item->label) }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Icono (clase Bootstrap/Icon)</label>
        <input type="text" name="icon_class" class="form-control" value="{{ old('icon_class', $item->icon_class) }}" placeholder="bi bi-gear">
      </div>
      <div class="col-md-4">
        <label class="form-label">Sección</label>
        <input list="sections" name="section" class="form-control" required value="{{ old('section', $item->section) }}" placeholder="ej. admin, hr, ventas">
        <datalist id="sections">
          @foreach(($sections ?? collect()) as $sec)
            <option value="{{ $sec }}"></option>
          @endforeach
        </datalist>
        <div class="form-text">Puedes usar cualquier etiqueta de sección (no está limitada a admin/professional/user/common).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Route name</label>
        <input type="text" name="route_name" class="form-control" value="{{ old('route_name', $item->route_name) }}" placeholder="admin.users">
      </div>
      <div class="col-md-4">
        <label class="form-label">URL (si no hay route)</label>
        <input type="text" name="url" class="form-control" value="{{ old('url', $item->url) }}" placeholder="/path">
      </div>
      <div class="col-md-4">
        <label class="form-label">Permiso (opcional)</label>
        <select name="permission" class="form-select">
          <option value="">— Ninguno —</option>
          @php $selPerm = old('permission', $item->permission); @endphp
          @foreach(($perms ?? collect()) as $p)
            @php $name = is_array($p) ? ($p['name'] ?? '') : ($p->name ?? ''); @endphp
            <option value="{{ $name }}" @selected($selPerm === $name)>{{ $name }}</option>
          @endforeach
        </select>
        <div class="form-text">Elige un permiso existente (Spatie). <a href="{{ route('admin.permissions.index') }}" target="_blank">Gestionar permisos</a></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Orden</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', (int)($item->sort_order ?? 0)) }}" min="0">
      </div>
      <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" name="enabled" id="enabledCheck" value="1" @checked(old('enabled', (bool)$item->enabled))>
          <label class="form-check-label" for="enabledCheck">Habilitado</label>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label d-block">Visible para roles</label>
        <div class="d-flex flex-wrap gap-3">
          @foreach($roles as $r)
            @php $rid = (int)$r->id; @endphp
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="role_ids[]" id="role_{{ $rid }}" value="{{ $rid }}" @checked(in_array($rid, (array)$selected, true))>
              <label class="form-check-label" for="role_{{ $rid }}">{{ $r->name }}</label>
            </div>
          @endforeach
        </div>
        <div class="form-text">Puedes elegir varios. Si no seleccionas roles, el ítem será visible para todos.</div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="{{ route('admin.menuitems.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button class="btn btn-primary" type="submit">Guardar</button>
  </div>
</form>

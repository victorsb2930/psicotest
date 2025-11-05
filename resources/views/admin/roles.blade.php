@extends('layout')
@section('title','Roles')
@section('page', 'admin-roles')
@section('content')
<div class="container-fluid py-4">

	<h1 class="mb-3">Roles</h1>
	@include('admin._flash')
	<div class="row g-4">
                <div class="col-12 col-lg-4">
                    	<div class="card card-min-lg">
                    	<div class="card-header">Crear nuevo rol</div>
				<div class="card-body">
					<form method="POST" action="{{ route('admin.roles.store') }}">
						@csrf
						<div class="mb-2">
							<label class="form-label">Nombre (slug)</label>
							<input type="text" name="name" class="form-control" required>
						</div>
						<div class="mb-2">
							<label class="form-label">Etiqueta para registro (opcional)</label>
							<input type="text" name="signup_label" class="form-control" placeholder="Usuario / Profesional / ...">
						</div>
						<div class="mb-2">
							<label class="form-label">Ruta de inicio (opcional)</label>
							<input type="text" name="home_path" class="form-control" placeholder="/userarea o nombre_de_ruta">
							<div class="form-text">Puedes poner una ruta interna (p.ej. /adminarea) o un nombre de ruta (p.ej. adminarea).</div>
						</div>
						<div class="row g-2">
							<div class="col-12 col-md-6">
								<label class="form-label">Icono (Bootstrap Icons)</label>
								<div class="input-group">
									<input id="icon_input_new" type="text" name="icon_class" class="form-control" placeholder="bi bi-people">
									<button class="btn btn-outline-secondary" type="button" data-role="open-icon-picker" data-target="icon_input_new">Elegir icono</button>
								</div>
							</div>
							<div class="col-12 col-md-6">
								<label class="form-label">Color del badge</label>
								<div class="input-group">
									<input id="badge_color_input_new" type="text" name="badge_color" class="form-control" placeholder="bg-primary / #0d6efd">
									<button class="btn btn-outline-secondary" type="button" data-role="open-color-picker" data-target="badge_color_input_new">Elegir color</button>
								</div>
							</div>
						</div>
						<div class="form-check form-switch mb-2">
							<input class="form-check-input" type="checkbox" name="show_in_signup" value="1" id="show_in_signup_new">
							<label class="form-check-label" for="show_in_signup_new">Mostrar en registro</label>
						</div>
						<div class="form-check form-switch mb-3">
							<input class="form-check-input" type="checkbox" name="requires_docs" value="1" id="requires_docs_new">
							<label class="form-check-label" for="requires_docs_new">Requiere documentos</label>
						</div>
						<button class="btn btn-primary">Crear</button>
					</form>
				</div>
			</div>
		</div>
                <div class="col-12 col-lg-8">
                    	<div class="card card-min-lg">
                    	<div class="card-header">Roles existentes</div>
                    	<div class="card-body table-responsive">
                        		<table class="table align-middle roles-table w-100">
						<thead>
							<tr>
								<th>ID</th>
								<th>Datos</th>
								<th>Nombre</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach($roles as $role)
							<tr>
								<td>{{ $role->id }}</td>
								<td>
									<form method="POST" action="{{ route('admin.roles.update', $role) }}" class="">
										@csrf @method('PUT')
										<div class="row g-2 align-items-center">
											<div class="col-12 col-md-4">
												<label class="form-label mb-0">Nombre (slug)</label>
												<input type="text" name="name" class="form-control" value="{{ $role->name }}">
											</div>
											<div class="col-12 col-md-4">
												<label class="form-label mb-0">Etiqueta (registro)</label>
												<input type="text" name="signup_label" class="form-control" placeholder="Usuario / Profesional" value="{{ $role->signup_label }}">
											</div>
											<div class="col-12 col-md-4 d-flex gap-3 align-items-center">
												<div class="form-check form-switch">
													<input class="form-check-input" type="checkbox" name="show_in_signup" value="1" id="show_{{ $role->id }}" @checked($role->show_in_signup)>
													<label class="form-check-label" for="show_{{ $role->id }}">Mostrar</label>
												</div>
												<div class="form-check form-switch">
													<input class="form-check-input" type="checkbox" name="requires_docs" value="1" id="docs_{{ $role->id }}" @checked($role->requires_docs)>
													<label class="form-check-label" for="docs_{{ $role->id }}">Docs</label>
												</div>
											</div>
											<div class="col-12 col-md-4">
												<label class="form-label mb-0">Ruta de inicio</label>
												<input type="text" name="home_path" class="form-control" value="{{ $role->home_path }}" placeholder="/userarea o nombre_de_ruta">
											</div>
											<div class="col-12 col-md-4">
												<label class="form-label mb-0">Icono</label>
												<div class="input-group">
													<input id="icon_input_{{ $role->id }}" type="text" name="icon_class" class="form-control" value="{{ $role->icon_class }}" placeholder="bi bi-people">
													<button class="btn btn-outline-secondary" type="button" data-role="open-icon-picker" data-target="icon_input_{{ $role->id }}">Elegir</button>
												</div>
											</div>
											<div class="col-12 col-md-4">
												<label class="form-label mb-0">Color badge</label>
												<div class="input-group">
													<input id="badge_color_input_{{ $role->id }}" type="text" name="badge_color" class="form-control" value="{{ $role->badge_color }}" placeholder="bg-primary / #0d6efd">
													<button class="btn btn-outline-secondary" type="button" data-role="open-color-picker" data-target="badge_color_input_{{ $role->id }}">Elegir</button>
												</div>
											</div>
											<div class="col-12">
												<button class="btn btn-outline-primary">Guardar</button>
											</div>
										</div>
									</form>
								</td>
								<td>
									@php
										$raw = $role->badge_color ?? null;
										$useHex = $raw && \Illuminate\Support\Str::startsWith($raw, '#');
										$slug = $role->name ?? null;
										$badgeClass = $useHex ? '' : ($raw ?? match($slug){
											'admin' => 'bg-dark',
											'professional' => 'bg-success',
											'user' => 'bg-primary',
											default => 'bg-secondary',
										});
										$badgeStyle = $useHex ? ("background-color: {$raw};") : '';
										$icon = $role->icon_class ?? match($slug){
											'admin' => 'bi bi-shield-lock',
											'professional' => 'bi bi-briefcase',
											'user' => 'bi bi-person',
											default => 'bi bi-tag',
										};
									@endphp
									<div class="d-flex align-items-center nombre-cell">
										<i class="{{ $icon }} me-2"></i>
										<span class="badge {{ $badgeClass }} me-2" @if($badgeStyle) style="{{ $badgeStyle }}" @endif>{{ $role->name }}</span>
									</div>
								</td>
								<td class="text-end">
									<form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
										onsubmit="return confirm('¿Eliminar rol?')" class="d-inline">
										@csrf @method('DELETE')
										<button class="btn btn-outline-danger btn-sm">Eliminar</button>
									</form>
								</td>
							</tr>
							<tr>
								<td colspan="4">
									<form method="POST" action="{{ route('admin.roles.permissions', $role) }}"
										class="d-flex flex-wrap gap-2 align-items-center">
										@csrf
										<strong class="me-2">Permisos:</strong>
										@foreach($permissions as $perm)
											<label class="me-2">
												<input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
													@checked(collect($assigned[$role->id] ?? [])->contains($perm->id))>
												<span class="ms-1">{{ $perm->name }}</span>
											</label>
										@endforeach
										<button class="btn btn-sm btn-primary">Guardar permisos</button>
									</form>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>

				</div>
			</div>
		</div>
	</div>
</div>
@endsection
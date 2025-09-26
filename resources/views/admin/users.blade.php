@extends('layout')

@section('title','Admin - Usuarios')
@section('page','admin-users')

@section('content')
<div class="container py-4">

	<h1 class="mb-3 d-flex align-items-center gap-3">
		<span>Gestión de usuarios</span>
		<span class="ms-auto d-flex gap-2">
			<a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-primary">Roles</a>
			<a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-outline-secondary">Permisos</a>
		</span>
	</h1>
	@include('admin._flash')

	<form method="GET" action="{{ route('admin.users') }}" class="row g-2 align-items-end mb-3">
		<div class="col-12 col-md-4">
			<label class="form-label">Buscar</label>
			<input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Nombre, email o ID">
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label">Rol</label>
			<select name="role" class="form-select">
				<option value="">Todos</option>
				@foreach($roles as $r)
				<option value="{{ $r->id }}" @selected(($roleId ?? null)===$r->id)>{{ $r->signup_label ?? $r->name }} ({{ $r->name }})
				</option>
				@endforeach
			</select>
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label">Estado</label>
			<select name="status" class="form-select">
				<option value="">Todos</option>
				<option value="active" @selected(($status ?? '' )==='active' )>Activo</option>
				<option value="inactive" @selected(($status ?? '' )==='inactive' )>Inactivo</option>
			</select>
		</div>
		<div class="col-12 col-md-2 d-grid">
			<button class="btn btn-primary">Filtrar</button>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label">Tamaño</label>
			<select name="size" class="form-select">
				@foreach([10,20,50] as $opt)
				<option value="{{ $opt }}" @selected(($size ?? 20)===$opt)>{{ $opt }}</option>
				@endforeach
			</select>
		</div>
		<input type="hidden" name="sort" value="{{ $sort ?? 'id' }}">
		<input type="hidden" name="dir" value="{{ $dir ?? 'asc' }}">
	</form>

	<div class="table-responsive" style="overflow: visible;">
		<table class="table align-middle bg-white">
			<thead>
			@php
			$nextDir = ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc';
			$qs = request()->query();
			@endphp
				<tr>
					<th>Acciones</th>
					<th>
						@php
							$qs_id = array_merge($qs, ['sort'=>'id','dir'=>($sort==='id'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_id) }}" class="text-decoration-none">
							ID @if(($sort ?? '')==='id')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
					<th>
						@php
							$qs_name = array_merge($qs, ['sort'=>'name','dir'=>($sort==='name'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_name) }}" class="text-decoration-none">
							Nombre @if(($sort ?? '')==='name')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
					<th>
						@php
							$qs_email = array_merge($qs, ['sort'=>'email','dir'=>($sort==='email'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_email) }}" class="text-decoration-none">
							Email @if(($sort ?? '')==='email')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
					<th>Activo</th>
					<th>
						@php
							$qs_roles = array_merge($qs, ['sort'=>'roles','dir'=>($sort==='roles'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_roles) }}" class="text-decoration-none">
							Roles @if(($sort ?? '')==='roles')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
					<th>
						@php
							$qs_created = array_merge($qs, ['sort'=>'created_at','dir'=>($sort==='created_at'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_created) }}" class="text-decoration-none">
							Creado @if(($sort ?? '')==='created_at')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
					<th>
						@php
							$qs_updated = array_merge($qs, ['sort'=>'updated_at','dir'=>($sort==='updated_at'?$nextDir:'asc')]);
						@endphp
						<a href="{{ route('admin.users', $qs_updated) }}" class="text-decoration-none">
							Última mod. @if(($sort ?? '')==='updated_at')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
						</a>
					</th>
				</tr>
			</thead>
			<tbody>
				@foreach($users as $u)
				<tr>
					@php
						// Determine active state early so it can be used by the actions dropdown
						$isActive = null;
						if (isset($u->is_active)) {
							$isActive = (bool) $u->is_active;
						} elseif (isset($u->is_banned)) {
							$isActive = !$u->is_banned;
						} else {
							// default to true if no column exists
							$isActive = true;
						}
					@endphp
					<td>
						<div class="dropdown">
							<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="actionsMenu{{ $u->id }}" data-bs-toggle="dropdown" aria-expanded="false" title="Más opciones">
								<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
							</button>
							<ul class="dropdown-menu" aria-labelledby="actionsMenu{{ $u->id }}">
								<li><a class="dropdown-item" href="mailto:{{ $u->email }}">Contactar por email</a></li>
								<li><button class="dropdown-item action-show-sessions" type="button" data-user-id="{{ $u->id }}">Mostrar historial de sesiones</button></li>
								<li><button class="dropdown-item disabled" type="button" title="Próximamente">Contactar por chat (próximamente)</button></li>
								@if(Route::has('admin.users.ban'))
								<li><button class="dropdown-item action-deactivate" type="button" data-user-id="{{ $u->id }}" data-user-name="{{ e($u->name) }}">{{ $isActive ? 'Desactivar' : 'Activar' }}</button></li>
								@endif
								@if(Route::has('admin.users.destroy'))
								<li>
									<button class="dropdown-item text-danger action-delete" type="button"
										data-delete-url="{{ route('admin.users.destroy', $u) }}"
										data-user-name="{{ e($u->name) }}"
										@if(auth()->id() === $u->id) disabled title="No puedes eliminar tu propia cuenta" @endif
									>Eliminar</button>
								</li>
								@endif
							</ul>
						</div>
					</td>
					<td>{{ $u->id }}</td>
					<td>{{ $u->name }}</td>
					<td>{{ $u->email }}</td>

					<td>
						@if($isActive)
							<span class="badge bg-success" @if(!empty($u->deactivated_reason)) data-bs-toggle="tooltip" title="{{ $u->deactivated_reason }}" @endif>Activo</span>
						@else
							<span class="badge bg-secondary" @if(!empty($u->deactivated_reason)) data-bs-toggle="tooltip" title="{{ $u->deactivated_reason }}" @endif>Inactivo</span>
						@endif
					</td>
					<td>
						<div class="mb-1 text-muted small">Asignados: <span class="badge bg-light text-dark">{{ $u->roles_count ?? $u->roles->count() }}</span></div>
						<form action="{{ route('admin.users.roles', $u) }}" method="POST" class="d-flex gap-2 align-items-center">
							@csrf
							<select name="roles[]" class="form-select form-select-sm" style="min-width:260px">
								@foreach($roles as $r)
								<option value="{{ $r->id }}" {{ $u->roles->contains('id', $r->id) ? 'selected' : '' }}>
									{{ $r->signup_label ?? $r->name }} ({{ $r->name }})
								</option>
								@endforeach
							</select>
							<button class="btn btn-sm btn-primary" type="submit">Guardar</button>
						</form>
					</td>
					<td>{{ $u->created_at?->format('Y-m-d H:i') ?? '' }}</td>
					<td>{{ $u->updated_at?->format('Y-m-d H:i') ?? '' }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	<div class="mt-3">
		{{ $users->links() }}
	</div>
</div>

<!-- Deactivation modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="deactivateForm" method="POST" action="">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Confirmar desactivación</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p id="deactivateMessage">¿Estás seguro de desactivar la cuenta?</p>
					<div class="mb-3">
						<label class="form-label">Motivo (opcional)</label>
						<textarea name="reason" id="deactivateReason" class="form-control" rows="3" placeholder="Describe el motivo de la desactivación"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="deactivateConfirmBtn">Confirmar</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="deleteUserForm" method="POST" action="">
				@csrf
				@method('DELETE')
				<div class="modal-header">
					<h5 class="modal-title">Confirmar eliminación</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p id="deleteUserMessage">¿Estás seguro de eliminar este usuario?</p>
					<p class="small text-muted">Eliminar aquí realiza un soft-delete (se mueve a la papelera de la base de datos). Podrás restaurarlo desde allí si es necesario.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-danger">Eliminar</button>
				</div>
			</form>
		</div>
	</div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
	// initialize bootstrap tooltips for badges showing deactivation reasons
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });

	// Deactivation modal
	const deactivateModalEl = document.getElementById('deactivateModal');
	const bsDeactivateModal = deactivateModalEl ? new bootstrap.Modal(deactivateModalEl) : null;
	document.querySelectorAll('.action-deactivate').forEach(function(btn){
		btn.addEventListener('click', function(){
			const userId = btn.getAttribute('data-user-id');
			const userName = btn.getAttribute('data-user-name');
			const form = document.getElementById('deactivateForm');
			form.action = '{{ url('/admin/users') }}/' + userId + '/ban';
			document.getElementById('deactivateMessage').textContent = 'Vas a cambiar el estado de ' + userName + '. Si estás desactivando, por favor indica la razón.';
			document.getElementById('deactivateReason').value = '';
			bsDeactivateModal && bsDeactivateModal.show();
		});
	});

	// Show sessions history modal - single handler
	document.querySelectorAll('.action-show-sessions').forEach(function(btn){
		btn.addEventListener('click', function(){
			const userId = btn.getAttribute('data-user-id');
			const url = '{{ url('/admin/users') }}/' + userId + '/sessions';
			window.axios.get(url).then(function(resp){
				const data = resp.data || {};
				if (!data.ok) {
					window.modalConfirm('No se pudo obtener el historial de sesiones', 'normal', { centered: true });
					return;
				}
				const sessions = data.sessions || [];
				// compute first (oldest started) and last (most recent started)
				let first = '-'; let last = '-';
				if (sessions.length) {
					first = sessions[sessions.length-1].started_at ?? '-';
					last = sessions[0].started_at ?? '-';
				}
				let html = `<div class="mb-3"><strong>Primer acceso:</strong> ${first}<br><strong>Último acceso:</strong> ${last}</div>`;
				html += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Inicio</th><th>Fin</th><th>Duración (s)</th><th>IP</th><th>Agente</th></tr></thead><tbody>`;
				sessions.forEach(function(s){
					const started = s.started_at ?? '-';
					const ended = s.ended_at ?? '-';
					html += `<tr><td>${started}</td><td>${ended}</td><td>${s.duration_seconds ?? '-'}</td><td>${s.ip ?? '-'}</td><td><small class="text-muted">${s.user_agent ?? '-'}</small></td></tr>`;
				});
				html += `</tbody></table></div>`;
				window.modalConfirm(html, 'normal', { centered: true, scrollable: true, size: 'lg' });
			}).catch(function(){
				window.modalConfirm('Error al consultar historial', 'normal', { centered: true });
			});
		});
	});

	// Delete modal trigger handled elsewhere via action-delete buttons
});
</script>
@endpush
@endsection
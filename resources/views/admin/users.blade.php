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

	<div class="table-responsive">
		<table class="table align-middle bg-white">
			<thead>
				@php
				$nextDir = ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc';
				$qs = request()->query();
				@endphp
				<tr>
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
										<th>
												@php
													$qs_active = array_merge($qs, ['sort'=>'active','dir'=>($sort==='active'?$nextDir:'asc')]);
												@endphp
												<a href="{{ route('admin.users', $qs_active) }}" class="text-decoration-none">
													Activo @if(($sort ?? '')==='active')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
												</a>
										</th>
										<th>
												@php
													$qs_roles = array_merge($qs, ['sort'=>'roles','dir'=>($sort==='roles'?$nextDir:'asc')]);
												@endphp
												<a href="{{ route('admin.users', $qs_roles) }}" class="text-decoration-none">
													Roles @if(($sort ?? '')==='roles')<span>{{ ($dir ?? 'asc')==='asc'?'▲':'▼' }}</span>@endif
												</a>
										</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody>
				@foreach($users as $u)
				<tr>
					<td>{{ $u->id }}</td>
					<td>{{ $u->name }}</td>
					<td>{{ $u->email }}</td>
					<td>
						<form action="{{ route('admin.users.toggle', $u) }}" method="POST" class="d-inline">
							@csrf
							<button class="btn btn-sm {{ $u->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
								type="submit">
								{{ $u->is_active ? 'Activo' : 'Inactivo' }}
							</button>
						</form>
					</td>
					<td>
						<div class="mb-1 text-muted small">Asignados: <span class="badge bg-light text-dark">{{ $u->roles_count ?? $u->roles->count() }}</span></div>
						<form action="{{ route('admin.users.roles', $u) }}" method="POST"
							class="d-flex gap-2 align-items-center">
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
					<td>
						<a class="btn btn-sm btn-light" href="mailto:{{ $u->email }}">Contactar</a>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	<div class="mt-3">
		{{ $users->links() }}
	</div>
</div>
@endsection
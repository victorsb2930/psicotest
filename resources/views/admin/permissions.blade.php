@extends('layout')
@section('title','Permisos')
@section('content')
<div class="container py-4">

	<h1 class="mb-3">Permisos</h1>
	@include('admin._flash')
	<div class="row g-4">
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-header">Crear nuevo permiso</div>
				<div class="card-body">
					<form method="POST" action="{{ route('admin.permissions.store') }}">
						@csrf
						<div class="mb-2">
							<label class="form-label">Nombre (slug)</label>
							<input type="text" name="name" class="form-control" required>
						</div>
						<button class="btn btn-primary">Crear</button>
					</form>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-header">Permisos existentes</div>
				<div class="card-body table-responsive">
					<table class="table table-sm align-middle">
						<thead>
							<tr>
								<th>ID</th>
								<th>Nombre</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach($permissions as $permission)
							<tr>
								<td>{{ $permission->id }}</td>
								<td>
									<form method="POST" action="{{ route('admin.permissions.update', $permission) }}"
										class="d-flex gap-2">
										@csrf @method('PUT')
										<input type="text" name="name" class="form-control form-control-sm"
											value="{{ $permission->name }}">
										<button class="btn btn-sm btn-outline-primary">Guardar</button>
									</form>
								</td>
								<td class="text-end">
									<form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}"
										onsubmit="return confirm('¿Eliminar permiso?')" class="d-inline">
										@csrf @method('DELETE')
										<button class="btn btn-sm btn-outline-danger">Eliminar</button>
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
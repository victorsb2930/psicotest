@extends('layout')

@section('title','Admin Area')
@section('page','admin')

@section('head')
@vite(['resources/css/admin.css'])
@endsection

@section('content')
<div class="py-3">

	@include('admin._flash')
	<h1 class="mb-3">Área de Administración</h1>
	<p class="text-600">Solo usuarios con rol/permisos de admin.</p>

			@if(isset($health) && !$health['ok'])
			<div class="alert alert-warning" role="alert">
				<strong>Configuración incompleta detectada:</strong>
				<ul class="mb-0 mt-2">
					@foreach(($health['messages'] ?? []) as $msg)
					<li>{{ $msg }}</li>
					@endforeach
				</ul>
			</div>
			@endif

			<div class="row g-3 mb-4">
				<div class="col-6 col-lg-3">
					<div class="card text-center shadow-sm border-0">
						<div class="card-body">
							<div class="text-muted d-flex align-items-center justify-content-center gap-2">
								<span class="bi bi-journal-check"></span>
								<span>Solicitudes Pro pendientes</span>
							</div>
							<div class="display-6">{{ $totals['prof_pending'] ?? 0 }}</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-lg-3">
					<div class="card text-center shadow-sm border-0">
						<div class="card-body">
							<div class="text-muted d-flex align-items-center justify-content-center gap-2">
								<span class="bi bi-people"></span>
								<span>Usuarios</span>
							</div>
							<div class="display-6">{{ $totals['users'] ?? 0 }}</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-lg-3">
					<div class="card text-center shadow-sm border-0">
						<div class="card-body">
							<div class="text-muted d-flex align-items-center justify-content-center gap-2">
								<span class="bi bi-check-circle"></span>
								<span>Activos</span>
							</div>
							<div class="display-6">{{ $totals['active'] ?? 0 }}</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-lg-3">
					<div class="card text-center shadow-sm border-0">
						<div class="card-body">
							<div class="text-muted d-flex align-items-center justify-content-center gap-2">
								<span class="bi bi-pause-circle"></span>
								<span>Inactivos</span>
							</div>
							<div class="display-6">{{ $totals['inactive'] ?? 0 }}</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-lg-3">
					<div class="card text-center shadow-sm border-0">
						<div class="card-body">
							<div class="text-muted d-flex align-items-center justify-content-center gap-2">
								<span class="bi bi-trash"></span>
								<span>Eliminados</span>
							</div>
							<div class="display-6">{{ $totals['deleted'] ?? 0 }}</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row g-3 mb-4">
				<div class="col-12 col-lg-6">
					<div class="card h-100">
						<div class="card-header bg-white fw-semibold">Usuarios por rol</div>
						<div class="card-body">
							<ul class="list-group list-group-flush">
								@foreach(($totals['byRole'] ?? []) as $row)
								@php
								$raw = $row->badge_color ?? null;
								$useHex = $raw && \Illuminate\Support\Str::startsWith($raw, '#');
								$slug = $row->slug ?? ($row->name ?? null);
								$badgeClass = $useHex ? '' : ($raw ?? match($slug){
								'admin' => 'bg-dark',
								'professional' => 'bg-success',
								'user' => 'bg-primary',
								default => 'bg-secondary',
								});
								$badgeStyle = $useHex ? ("background-color: {$raw};") : '';
								$icon = $row->icon_class ?? match($slug){
								'admin' => 'bi bi-shield-lock',
								'professional' => 'bi bi-briefcase',
								'user' => 'bi bi-person',
								default => 'bi bi-tag',
								};
								@endphp
								<li class="list-group-item d-flex justify-content-between align-items-center">
									<span>
										<i class="{{ $icon }} me-2"></i>
										<span class="badge {{ $badgeClass }} me-2" @if($badgeStyle)
											style="{{ $badgeStyle }}" @endif>{{ $slug ?? 'rol' }}</span>
										{{ $row->signup_label ?? ($row->name ?? 'Rol') }}
									</span>
									<span class="badge bg-light text-dark">{{ $row->users }}</span>
								</li>
								@endforeach
							</ul>
						</div>
					</div>
				</div>
				<div class="col-12 col-lg-6">
					<div class="card h-100 shadow-sm border-0">
						<div class="card-header bg-white fw-semibold">Catálogo</div>
						<div class="card-body">
							<div class="d-flex flex-wrap gap-3">
								<div>
									<div class="text-muted">Roles</div>
									<div class="h3 mb-0">{{ $totals['roles'] ?? 0 }}</div>
								</div>
								<div>
									<div class="text-muted">Permisos</div>
									<div class="h3 mb-0">{{ $totals['permissions'] ?? 0 }}</div>
								</div>
							</div>
							<hr>
							<div>
								<div class="text-muted mb-2">Accesos rápidos</div>
								<div class="d-flex flex-wrap gap-2">
									@foreach(($areas ?? []) as $area)
									<a class="btn {{ $area['btn'] }}" href="{{ route($area['name']) }}">{{
										$area['label'] }}</a>
									@endforeach
									@can('adminarea')
									@if(\Illuminate\Support\Facades\Route::has('admin.profapps.index'))
									<a class="btn btn-outline-warning position-relative"
										href="{{ route('admin.profapps.index') }}">
										Solicitudes Pro
										@if(($totals['prof_pending'] ?? 0) > 0)
										<span
											class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{
											$totals['prof_pending'] }}</span>
										@endif
									</a>
									@endif
									@endcan
									@if(empty($areas))
									<span class="text-muted">No hay áreas disponibles para tu usuario.</span>
									@endif
								</div>
							</div>
							<hr>
							<div>
								<div class="text-muted mb-2">Tus permisos</div>
								<div class="d-flex flex-wrap gap-2">
									@php
									$__allPerms = \Spatie\Permission\Models\Permission::orderBy('name')->get(['name']);
									@endphp
									@foreach($__allPerms as $__p)
									@can($__p->name)
									<span class="badge bg-light text-dark" title="{{ $__p->name }}">{{ $__p->name
										}}</span>
									@endcan
									@endforeach
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

	</div>

	@endsection

	@push('scripts')
	@endpush
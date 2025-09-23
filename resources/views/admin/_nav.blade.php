<nav class="mb-3">
	@php
	$is = function($name) {
		return request()->routeIs($name);
	};
	@endphp
	<ul class="nav nav-pills gap-2">
		<li class="nav-item">
			<a class="nav-link {{ $is('adminarea') ? 'active' : '' }}" href="{{ route('adminarea') }}">Dashboard</a>
		</li>
		<li class="nav-item">
			<a class="nav-link {{ $is('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">Usuarios</a>
		</li>
		<li class="nav-item">
			<a class="nav-link {{ $is('admin.roles.*') ? 'active' : '' }}"
				href="{{ route('admin.roles.index') }}">Roles</a>
			<a class="nav-link position-relative {{ $is('admin.profapps.*') ? 'active' : '' }}"
				href="{{ route('admin.profapps.index') }}">
				Solicitudes Pro
				@php
					$__pending = \Illuminate\Support\Facades\Schema::hasTable('professional_applications')
						? \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count()
						: 0;
				@endphp
				
				@if($__pending > 0)
					<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $__pending }}</span>
				@endif
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link {{ $is('admin.permissions.*') ? 'active' : '' }}"
				href="{{ route('admin.permissions.index') }}">Permisos</a>
		</li>
	</ul>

	@if(!empty($areas ?? []))
		<div class="mt-2">
			@foreach(($areas ?? []) as $area)
				@php
					$areaHref = (isset($area['name']) && \Illuminate\Support\Facades\Route::has($area['name']))
						? route($area['name'])
						: ($area['url'] ?? '#');
				@endphp
				<a class="btn {{ $area['btn'] ?? 'btn-outline-primary' }} me-1 mb-1" href="{{ $areaHref }}">{{ $area['label'] ?? 'Área' }}</a>
			@endforeach
		</div>
	@endif
	<style>
		.nav-pills .nav-link.active {
			background-color: var(--bs-primary);
		}
	</style>
</nav>
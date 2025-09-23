<nav class="mb-3">
	@php
	$is = fn($name) => request()->routeIs($name);
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
				@php($__pending = \Illuminate\Support\Facades\Schema::hasTable('professional_applications')
					? \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count()
					: 0)
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
	<style>
		.nav-pills .nav-link.active {
			background-color: var(--bs-primary);
		}
	</style>
</nav>
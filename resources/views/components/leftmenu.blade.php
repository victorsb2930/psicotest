<aside id="left-menu" class="d-none d-lg-block col-lg-3">
    <div class="card h-100 border-0 shadow-sm">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a class="d-flex align-items-center text-decoration-none" href="{{ auth()->check() ? (auth()->user()->hasRole('admin') ? route('adminarea') : (auth()->user()->hasRole('professional') ? route('professionalarea') : route('userarea'))) : '/' }}">
                    <div class="me-2"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                    <div class="fw-semibold">Panel</div>
                </a>
                @php
                    $showBack = !(request()->routeIs('adminarea') || request()->routeIs('professionalarea') || request()->routeIs('userarea'));
                @endphp
                <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary ms-2" aria-label="Atrás" style="display: {{ $showBack ? 'inline-flex' : 'none' }}">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>

            @php
                $is = function($name) { return request()->routeIs($name); };
                $profPending = \Illuminate\Support\Facades\Schema::hasTable('professional_applications')
                    ? \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count()
                    : 0;
            @endphp

            <ul class="nav flex-column gap-2">
                @can('adminarea')
					<hr class="sidebar-divider my-0">
					<li class="nav-item"><a href="{{ route('adminarea') }}" class="nav-link px-0 {{ $is('adminarea') ? 'active' : '' }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
					<hr class="sidebar-divider my-0">
					<li class="nav-item"><a href="{{ route('admin.users') }}" class="nav-link px-0 {{ $is('admin.users') ? 'active' : '' }}"><i class="bi bi-people me-2"></i>Usuarios</a></li>
					<li class="nav-item position-relative">
						<a href="{{ route('admin.profapps.index') }}" class="nav-link px-0 {{ $is('admin.profapps.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-medical me-2"></i>Solicitudes</a>
						@if($profPending > 0)
							<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $profPending }}</span>
						@endif
					</li>
					<li class="nav-item"><a href="{{ route('admin.roles.index') }}" class="nav-link px-0 {{ $is('admin.roles.*') ? 'active' : '' }}"><i class="bi bi-shield-lock me-2"></i>Roles</a></li>
					<li class="nav-item"><a href="{{ route('admin.permissions.index') }}" class="nav-link px-0 {{ $is('admin.permissions.*') ? 'active' : '' }}"><i class="bi bi-key me-2"></i>Permisos</a></li>
					<li class="nav-item"><a href="#catalog" class="nav-link px-0"><i class="bi bi-book me-2"></i>Catálogo</a></li>
                @else
                    @if(auth()->check() && auth()->user()->hasRole('professional'))
                        <li class="nav-item"><a href="{{ route('professional.appointments') ?? '#' }}" class="nav-link px-0"><i class="bi bi-calendar-check me-2"></i>Citas</a></li>
                        <li class="nav-item"><a href="#calendar" class="nav-link px-0"><i class="bi bi-calendar3 me-2"></i>Calendario</a></li>
                        <li class="nav-item"><a href="#chat" class="nav-link px-0"><i class="bi bi-chat-dots me-2"></i>Chat</a></li>
                        <li class="nav-item"><a href="#qa" class="nav-link px-0"><i class="bi bi-question-circle me-2"></i>Preguntas &amp; Respuestas</a></li>
                    @else
                        <li class="nav-item"><a href="#" class="nav-link px-0"><i class="bi bi-calendar-check me-2"></i>Citas</a></li>
                        <li class="nav-item"><a href="#calendar" class="nav-link px-0"><i class="bi bi-calendar3 me-2"></i>Calendario</a></li>
                        <li class="nav-item"><a href="#chat" class="nav-link px-0"><i class="bi bi-chat-dots me-2"></i>Chat</a></li>
                        <li class="nav-item"><a href="#qa" class="nav-link px-0"><i class="bi bi-question-circle me-2"></i>Preguntas &amp; Respuestas</a></li>
                    @endif
                @endcan
            </ul>
        </div>
    </div>
</aside>

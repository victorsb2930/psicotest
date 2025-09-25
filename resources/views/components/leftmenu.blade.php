<aside id="left-menu" class="d-none d-lg-block col-lg-3">
	<div class="card h-100 border-0 shadow-sm">
		<div class="card-body py-3">
			<div class="d-flex align-items-center justify-content-between mb-3">
				@php
					$homeUrl = auth()->check()
						? (auth()->user()->hasRole('admin')
							? route('adminarea')
							: (auth()->user()->hasRole('professional')
								? route('professionalarea')
								: route('userarea')))
						: '/';
					$isAdmin = auth()->check() && auth()->user()->hasRole('admin');
				@endphp

				<a class="d-flex align-items-center text-white text-decoration-none" href="{{ $homeUrl }}">
					<div class="me-2"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
					<div class="fw-semibold text-white">{{ $isAdmin ? 'Panel' : 'Menú' }}</div>
				</a>
			</div>

			@php
				$is = function($name) { return request()->routeIs($name); };
				$profPending = \Illuminate\Support\Facades\Schema::hasTable('professional_applications')
					? \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count()
					: 0;
			@endphp

			<ul class="nav flex-column gap-2">
				{{-- ADMIN SECTION --}}
				@can('adminarea')
					<hr class="sidebar-divider my-0">
					<li class="nav-item">
						<a href="{{ Route::has('adminarea') ? route('adminarea') : '#' }}" class="nav-link px-0 {{ $is('adminarea') ? 'active' : '' }}">
							<i class="bi bi-speedometer2 me-2"></i>Dashboard
						</a>
					</li>
					<hr class="sidebar-divider my-0">
					<li class="nav-item">
						<a href="{{ Route::has('admin.users') ? route('admin.users') : '#' }}" class="nav-link px-0 {{ $is('admin.users') ? 'active' : '' }}">
							<i class="bi bi-people me-2"></i>Usuarios
						</a>
					</li>
					<li class="nav-item position-relative">
						<a href="{{ Route::has('admin.profapps.index') ? route('admin.profapps.index') : '#' }}" class="nav-link px-0 {{ $is('admin.profapps.*') ? 'active' : '' }}">
							<i class="bi bi-file-earmark-medical me-2"></i>Solicitudes
						</a>
						@if($profPending > 0)
							<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $profPending }}</span>
						@endif
					</li>
					<li class="nav-item">
						<a href="{{ Route::has('admin.roles.index') ? route('admin.roles.index') : '#' }}" class="nav-link px-0 {{ $is('admin.roles.*') ? 'active' : '' }}">
							<i class="bi bi-shield-lock me-2"></i>Roles
						</a>
					</li>
					<li class="nav-item">
						<a href="{{ Route::has('admin.permissions.index') ? route('admin.permissions.index') : '#' }}" class="nav-link px-0 {{ $is('admin.permissions.*') ? 'active' : '' }}">
							<i class="bi bi-key me-2"></i>Permisos
						</a>
					</li>

				{{-- PROFESSIONAL / USER SECTION --}}
				@else
					@if(auth()->check() && auth()->user()->hasRole('professional'))
						{{-- If the user also has admin role, offer a quick link back to admin area when in professional area --}}
						@if(auth()->user()->hasRole('admin') && $is('professionalarea'))
							<li class="nav-item">
								<a href="{{ Route::has('adminarea') ? route('adminarea') : '#' }}" class="nav-link px-0">
									<i class="bi bi-arrow-left-circle me-2"></i>Volver a admin
								</a>
							</li>
						@endif

						<li class="nav-item">
							<a href="{{ Route::has('professionalarea') ? route('professionalarea') : '#' }}" class="nav-link px-0 {{ $is('professionalarea') ? 'active' : '' }}">
								<i class="bi bi-person-badge me-2"></i>Mi panel
							</a>
						</li>
						<li class="nav-item"><a href="{{ Route::has('professional.calendar') ? route('professional.calendar') : '#' }}" class="nav-link px-0 {{ $is('professional.calendar') ? 'active' : '' }}"><i class="bi bi-calendar3 me-2"></i>Calendario</a></li>
						<li class="nav-item"><a href="{{ Route::has('professional.appointments') ? route('professional.appointments') : '#' }}" class="nav-link px-0 {{ $is('professional.appointments') ? 'active' : '' }}"><i class="bi bi-calendar-check me-2"></i>Citas</a></li>
						<li class="nav-item"><a href="{{ Route::has('messages.index') ? route('messages.index') : '#' }}" class="nav-link px-0 {{ $is('messages.index') ? 'active' : '' }}"><i class="bi bi-chat-dots me-2"></i>Mensajes</a></li>
						<li class="nav-item"><a href="{{ Route::has('professional.patients') ? route('professional.patients') : '#' }}" class="nav-link px-0 {{ $is('professional.patients') ? 'active' : '' }}"><i class="bi bi-people me-2"></i>Pacientes</a></li>
						<li class="nav-item"><a href="{{ Route::has('professional.services') ? route('professional.services') : '#' }}" class="nav-link px-0 {{ $is('professional.services') ? 'active' : '' }}"><i class="bi bi-briefcase me-2"></i>Servicios</a></li>
						<li class="nav-item"><a href="{{ Route::has('professional.payments') ? route('professional.payments') : '#' }}" class="nav-link px-0 {{ $is('professional.payments') ? 'active' : '' }}"><i class="bi bi-credit-card me-2"></i>Pagos</a></li>
						<li class="nav-item"><a href="{{ Route::has('professional.settings') ? route('professional.settings') : '#' }}" class="nav-link px-0 {{ $is('professional.settings') ? 'active' : '' }}"><i class="bi bi-gear me-2"></i>Configuración</a></li>

					{{-- REGULAR USER SECTION --}}
					@else
						<li class="nav-item"><a href="{{ Route::has('userarea') ? route('userarea') : '#' }}" class="nav-link px-0 {{ $is('userarea') ? 'active' : '' }}"><i class="bi bi-house me-2"></i>Mi cuenta</a></li>
						<li class="nav-item"><a href="{{ Route::has('appointments.index') ? route('appointments.index') : '#' }}" class="nav-link px-0"><i class="bi bi-calendar-check me-2"></i>Citas</a></li>
						<li class="nav-item"><a href="{{ Route::has('search') ? route('search') : '#' }}" class="nav-link px-0"><i class="bi bi-search me-2"></i>Buscar profesionales</a></li>
						<li class="nav-item"><a href="{{ Route::has('favorites') ? route('favorites') : '#' }}" class="nav-link px-0"><i class="bi bi-star me-2"></i>Favoritos</a></li>
						<li class="nav-item"><a href="{{ Route::has('messages.index') ? route('messages.index') : '#' }}" class="nav-link px-0"><i class="bi bi-chat-dots me-2"></i>Mensajes</a></li>
					@endif
				@endcan

				{{-- COMMON LINKS --}}
				<hr class="sidebar-divider my-1">
				<li class="nav-item"><a href="{{ route('contact') }}" class="nav-link px-0"><i class="bi bi-envelope me-2"></i>Contacto</a></li>
				<li class="nav-item"><a href="{{ route('services') }}" class="nav-link px-0"><i class="bi bi-briefcase me-2"></i>Servicios</a></li>
			</ul>
		</div>
	</div>
</aside>

<script>
	(function(){
		const btn = document.getElementById('leftmenu-safe-back');
		if (!btn) return;
		const welcomePaths = ['/', '/welcome'];

		function updateBtnState() {
			try {
				const stack = Array.isArray(window.__navStack) ? window.__navStack : [];
				if (!window.__isAuth || stack.length <= 1) {
					btn.setAttribute('disabled', 'disabled');
					btn.classList.add('disabled');
					btn.title = 'No hay historial interno';
				} else {
					btn.removeAttribute('disabled');
					btn.classList.remove('disabled');
					btn.title = '';
				}
			} catch (e) { try { btn.setAttribute('disabled','disabled'); } catch(_){} }
		}

		btn.addEventListener('click', function(e){
			e.preventDefault();
			try {
				const stack = Array.isArray(window.__navStack) ? window.__navStack : [];
				if (!window.__isAuth || stack.length <= 1) {
					if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
					return;
				}
				stack.pop();
				let target = null;
				while (stack.length) {
					const cand = stack[stack.length - 1];
					if (welcomePaths.includes(cand)) { stack.pop(); continue; }
					target = cand; break;
				}
				window.__navStack = stack;
				try { sessionStorage.setItem('pg_nav_stack_v1', JSON.stringify(stack)); } catch(_){ }
				if (target) {
					if (window.__pjaxEnabled && window.history && window.history.length > 1) { history.back(); setTimeout(updateBtnState,300); return; }
					window.location.href = target;
				} else {
					updateBtnState();
					if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
				}
			} catch (err) { console.warn('leftmenu back error', err); }
		});

		updateBtnState();
		window.addEventListener('pg:navstack:changed', updateBtnState);
		let tries = 0; const iv = setInterval(()=>{ tries++; try{ updateBtnState(); }catch(_){} if(window.__pjaxEnabled || tries>40) clearInterval(iv); },100);
	})();
</script>
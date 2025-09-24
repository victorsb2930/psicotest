<aside id="left-menu" class="d-none d-lg-block col-lg-3">
	<div class="card h-100 border-0 shadow-sm">
		<div class="card-body py-3">
			<div class="d-flex align-items-center justify-content-between mb-3">
				@php
				$homeUrl = auth()->check() ? (auth()->user()->hasRole('admin') ? route('adminarea') :
				(auth()->user()->hasRole('professional') ? route('professionalarea') : route('userarea'))) : '/';
				$isAdmin = auth()->check() && auth()->user()->hasRole('admin');
				@endphp
				<a class="d-flex align-items-center text-white text-decoration-none"
					href="{{ $homeUrl }}">
					<div class="me-2"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
					@if($isAdmin)
						<div class="fw-semibold text-white">Panel</div>
					@endif
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
				<li class="nav-item"><a href="{{ route('adminarea') }}"
							class="nav-link px-0 {{ $is('adminarea') ? 'active' : '' }}"><i
								class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
				<hr class="sidebar-divider my-0">
				<li class="nav-item"><a href="{{ route('admin.users') }}"
						class="nav-link px-0 {{ $is('admin.users') ? 'active' : '' }}"><i
							class="bi bi-people me-2"></i>Usuarios</a></li>
				<li class="nav-item position-relative">
					<a href="{{ route('admin.profapps.index') }}"
						class="nav-link px-0 {{ $is('admin.profapps.*') ? 'active' : '' }}"><i
							class="bi bi-file-earmark-medical me-2"></i>Solicitudes</a>
					@if($profPending > 0)
					<span
						class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{
						$profPending }}</span>
					@endif
				</li>
				<li class="nav-item"><a href="{{ route('admin.roles.index') }}"
						class="nav-link px-0 {{ $is('admin.roles.*') ? 'active' : '' }}"><i
							class="bi bi-shield-lock me-2"></i>Roles</a></li>
				<li class="nav-item"><a href="{{ route('admin.permissions.index') }}"
						class="nav-link px-0 {{ $is('admin.permissions.*') ? 'active' : '' }}"><i
							class="bi bi-key me-2"></i>Permisos</a></li>
				<li class="nav-item"><a href="#catalog" class="nav-link px-0"><i
							class="bi bi-book me-2"></i>Catálogo</a></li>
				@else
				@if(auth()->check() && auth()->user()->hasRole('professional'))
				<li class="nav-item"><a href="{{ route('professional.appointments') ?? '#' }}" class="nav-link px-0"><i
							class="bi bi-calendar-check me-2"></i>Citas</a></li>
				<li class="nav-item"><a href="#calendar" class="nav-link px-0"><i
							class="bi bi-calendar3 me-2"></i>Calendario</a></li>
				<li class="nav-item"><a href="#chat" class="nav-link px-0"><i class="bi bi-chat-dots me-2"></i>Chat</a>
				</li>
				<li class="nav-item"><a href="#qa" class="nav-link px-0"><i
							class="bi bi-question-circle me-2"></i>Preguntas &amp; Respuestas</a></li>
				@else
				<li class="nav-item"><a href="#" class="nav-link px-0"><i
							class="bi bi-calendar-check me-2"></i>Citas</a></li>
				<li class="nav-item"><a href="#calendar" class="nav-link px-0"><i
							class="bi bi-calendar3 me-2"></i>Calendario</a></li>
				<li class="nav-item"><a href="#chat" class="nav-link px-0"><i class="bi bi-chat-dots me-2"></i>Chat</a>
				</li>
				<li class="nav-item"><a href="#qa" class="nav-link px-0"><i
							class="bi bi-question-circle me-2"></i>Preguntas &amp; Respuestas</a></li>
				@endif
				@endcan
			</ul>
		</div>
	</div>
</aside>
<script>
	// Improved Safe Back:
	// - Prefer history.back() when referrer is same-origin and not a public landing (/ or /welcome).
	// - If no safe referrer and PJAX isn't active, DO NOT navigate to public pages; instead disable the button.
	// - If PJAX becomes active shortly after load, re-enable the button when history suggests a back is possible.
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

		// Pop last path and navigate only to a safe internal path
		btn.addEventListener('click', function(e){
			e.preventDefault();
			try {
				const stack = Array.isArray(window.__navStack) ? window.__navStack : [];
				if (!window.__isAuth || stack.length <= 1) {
					// nothing to do
					if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
					return;
				}
				// Pop current
				stack.pop();
				// find previous that is not a welcome/root
				let target = null;
				while (stack.length) {
					const cand = stack[stack.length - 1];
					if (welcomePaths.includes(cand)) {
						// discard public landing entries
						stack.pop();
						continue;
					}
					target = cand; break;
				}
				// save reduced stack
				window.__navStack = stack;
				try { sessionStorage.setItem('pg_nav_stack_v1', JSON.stringify(stack)); } catch(_){}

				if (target) {
					// If PJAX is active, navigate using history.back() to preserve SPA behavior
					if (window.__pjaxEnabled && window.history && window.history.length > 1) {
						history.back();
						// PJAX popstate will swap content; update button state a bit later
						setTimeout(updateBtnState, 300);
						return;
					}
					// else navigate normally to the target (this will be a full navigation)
					window.location.href = target;
				} else {
					// no safe internal target left
					updateBtnState();
					if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
				}
			} catch (err) {
				console.warn('leftmenu back error', err);
			}
		});

		// init state and listen for nav stack changes from other scripts
		updateBtnState();
		window.addEventListener('pg:navstack:changed', updateBtnState);

		// small interval to wait for PJAX to initialize and potentially push entries
		let tries = 0;
		const iv = setInterval(() => {
			tries++;
			try { updateBtnState(); } catch(_){}
			if (window.__pjaxEnabled || tries > 40) clearInterval(iv);
		}, 100);
	})();
</script>
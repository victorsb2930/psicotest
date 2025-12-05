@extends('layout')
@section('title', 'PsicoTest: Inicia sesión o Registrate')
@section('page', 'login-register')
@section('content')
@if(auth()->check())
	<script>
	// Redirigir inmediatamente si el usuario está autenticado y llegó aquí por hash o navegación parcial
	(function(){
		try {
			var roleArea = @json(auth()->user()->hasRole('admin') ? route('adminarea') : (auth()->user()->hasRole('professional') ? route('professionalarea') : route('userarea')));
			if (window.location.pathname.indexOf('/welcome') === 0) {
				window.location.replace(roleArea);
			}
		} catch(e) {}
	})();
	</script>
	<noscript>
		<div class="alert alert-warning">Ya has iniciado sesión. <a href="{{ auth()->user()->hasRole('admin') ? route('adminarea') : (auth()->user()->hasRole('professional') ? route('professionalarea') : route('userarea')) }}">Ir a tu área</a>.</div>
	</noscript>
@else
@once
	@vite(['resources/css/loginRegister.css'])
@endonce
<div class="d-flex align-items-center justify-content-center" style="padding-top: 1%">
	<div class="login-container">
		<div class="box">
			<!-- FORMULARIO LOGIN -->
			<div class="form sign_in">
				<h3>Iniciar Sesión</h3>
				<form action="{{ route('login') }}" method="POST" id="login_form">
					@csrf
					<div class="field">
						<label for="login_email" class="field-label">Email</label>
						<div class="type">
							<input type="email" name="email" placeholder="Email" id="login_email" value="{{ old('email') }}" />
						</div>
					</div>
					<div class="field">
						<label for="login_password" class="field-label">Contraseña</label>
						<div class="type">
							<input type="password" name="password" placeholder="Contraseña" id="login_password" />
						</div>
					</div>
					<div class="forgot">
						<a href="{{ route('password.request') }}" class="text-decoration-underline">¿Olvidaste tu contraseña?</a>
					</div>
					<button id="login_submit_btn" class="btn bkg" type="submit">Iniciar Sesión</button>
				</form>
			</div>
			<!-- FORMULARIO REGISTRO -->
			<div class="form sign_up">
				<h3>Regístrate</h3>
				<form action="{{ route('register') }}" method="POST" id="register_form" enctype="multipart/form-data" novalidate>
					@csrf
					<div class="field">
						<label for="reg_photo" class="field-label">Foto de perfil</label>
						<div class="type">
							<input type="file" id="reg_photo" name="reg_photo" accept=".jpg,.jpeg,.png" />
						</div>
						<small class="field-help">Imagen opcional. Tamaño recomendado: 400x400.</small>
					</div>
					<div class="field">
						<label for="reg_type" class="field-label">Tipo de usuario</label>
						<div class="type">
							<select id="reg_type" name="reg_type" class="form-select bg-transparent border-0" aria-label="Tipo de registro"></select>
						</div>
					</div>
					<div class="field">
						<label for="reg_name" class="field-label">Nombres</label>
						<div class="type">
							<input type="text" placeholder="Nombre" id="reg_name" name="reg_name" value="{{ old('reg_name') }}" />
						</div>
					</div>
					<div class="field">
						<label for="reg_lastname" class="field-label">Apellidos</label>
						<div class="type">
							<input type="text" placeholder="Apellidos" id="reg_lastname" name="reg_lastname" value="{{ old('reg_lastname') }}" />
						</div>
					</div>
					<div class="field">
						<label for="reg_birthdate" class="field-label">Fecha de nacimiento</label>
						<div class="type">
							<input type="date" id="reg_birthdate" name="reg_birthdate" value="{{ old('reg_birthdate') }}" />
						</div>
					</div>
					<div class="field">
						<label for="reg_gender" class="field-label">Género</label>
						<div class="type">
							<select id="reg_gender" name="reg_gender" class="form-select bg-transparent border-0">
								<option value="">Selecciona una opción</option>
								<option value="masculino" @selected(old('reg_gender')==='masculino')>Masculino</option>
								<option value="femenino" @selected(old('reg_gender')==='femenino')>Femenino</option>
							</select>
						</div>
					</div>
					<div class="field">
						<label for="reg_email" class="field-label">Email</label>
						<div class="type">
							<input type="email" placeholder="Email" id="reg_email" name="reg_email" value="{{ old('reg_email') }}" />
						</div>
					</div>
					<div class="field">
						<label for="reg_password" class="field-label">Contraseña</label>
						<div class="type">
							<input type="password" placeholder="Contraseña" id="reg_password" name="reg_password" />
						</div>
						<small class="field-help">Mínimo 6 caracteres. Usa letras y números para mayor seguridad.</small>
					</div>
					<div class="field">
						<label for="reg_password_confirm" class="field-label">Confirmar Contraseña</label>
						<div class="type">
							<input type="password" placeholder="Confirmar Contraseña" id="reg_password_confirm" name="reg_password_confirmation" />
						</div>
					</div>
					<div class="field professional-only d-none">
						<label for="reg_speciality" class="field-label">Especialidad</label>
						<div class="type">
							<input type="text" placeholder="Ej. Psicología clínica" id="reg_speciality" name="reg_speciality" />
						</div>
					</div>
					<div class="field">
						<label for="reg_location" class="field-label">Ubicación</label>
						<div class="type">
							<input type="text" placeholder="Ciudad, provincia" id="reg_location" name="reg_location" value="{{ old('reg_location') }}" />
						</div>
					</div>
					<div class="field professional-only d-none">
						<label for="reg_titulo" class="field-label">Título profesional (escaneado)</label>
						<div class="type">
							<input type="file" id="reg_titulo" name="reg_titulo" accept=".pdf,.jpg,.jpeg,.png" />
						</div>
					</div>
					<div class="field professional-only d-none">
						<label for="reg_cedula" class="field-label">Cédula de identidad (escaneada)</label>
						<div class="type">
							<input type="file" id="reg_cedula" name="reg_cedula" accept=".pdf,.jpg,.jpeg,.png" />
						</div>
					</div>
					<div class="field professional-only d-none">
						<label for="reg_cv" class="field-label">Curriculum Vitae</label>
						<div class="type">
							<input type="file" id="reg_cv" name="reg_cv" accept=".pdf,.jpg,.jpeg,.png" />
						</div>
					</div>
					<div class="field professional-only d-none">
						<label for="reg_exequatur" class="field-label">Exequátur</label>
						<div class="type">
							<input type="file" id="reg_exequatur" name="reg_exequatur" accept=".pdf,.jpg,.jpeg,.png" />
						</div>
					</div>
					<button id="register_submit_btn" class="btn bkg" type="submit">Registrarte</button>
				</form>
			</div>
		</div>
		<div class="overlay">
			<div class="page page_signIn">
				<h3>¡Bienvenido de vuelta!</h3>
				<p>Si aun no tienes una cuenta con nosotros, puedes:</p>
				<button class="btn btnSign-in">Registrarte <i class="bi bi-arrow-right"></i></button>
			</div>
			<div class="page page_signUp">
				<h3>¡Hola amigo!</h3>
				<p>Si ya tienes una cuenta, puedes:</p>
				<button class="btn btnSign-up"><i class="bi bi-arrow-left"></i> Iniciar Sesión</button>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@if(session('success') || $errors->any())
	<script>
		window.__flash = {
			success: @json(session('success')),
			errors: @json($errors->all())
		};
	</script>
@endif

@if(isset($signupRoles))
<script>
  window.__signupRoles = @json($signupRoles);
</script>
@endif

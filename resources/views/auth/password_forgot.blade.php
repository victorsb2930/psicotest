@extends('layout')
@section('title', 'Recuperar contraseña')
@section('page', 'password-forgot')
@section('content')
<div class="container py-4" id="app-content">
	<div class="row justify-content-center">
		<div class="col-12 col-md-8 col-lg-6">
			<x-card :hover="false" :compact="true" title="¿Olvidaste tu contraseña?" subtitle="Te enviaremos un enlace para restablecerla" class="mt-3">
				@if(session('success'))
					<div class="alert alert-success">{{ session('success') }}</div>
				@endif
				@if($errors->any())
					<div class="alert alert-danger">
						<ul class="mb-0">
							@foreach($errors->all() as $e)
								<li>{{ $e }}</li>
							@endforeach
						</ul>
					</div>
				@endif
				<form method="POST" action="{{ route('password.email') }}" class="mt-2">
					@csrf
					<div class="mb-3">
						<label for="forgot_email" class="form-label">Email</label>
						<input type="email" name="email" id="forgot_email" class="form-control" placeholder="tu@correo.com" value="{{ old('email') }}" required autocomplete="email">
					</div>
					<div class="d-flex justify-content-between align-items-center">
						<a href="/welcome" class="text-decoration-underline">Volver a inicio</a>
						<button type="submit" class="btn btn-primary">Enviar enlace</button>
					</div>
				</form>
			</x-card>
		</div>
	</div>
</div>
@endsection

@extends('layout')
@section('title','Restablecer contraseña')
@section('page','password-reset')
@section('content')
<div class="container py-4">
	<div class="row justify-content-center">
		<div class="col-md-6">
			<div class="card shadow-sm">
				<div class="card-header">Restablecer contraseña</div>
				<div class="card-body">
					@if(session('status'))
					<div class="alert alert-success">{{ session('status') }}</div>
					@endif
					@if(session('error'))
					<div class="alert alert-danger">{{ session('error') }}</div>
					@endif
					@if($errors->any())
						<div class="alert alert-danger">
							<ul class="mb-0 ps-3">
							@foreach($errors->all() as $err)
								<li>{{ $err }}</li>
							@endforeach
							</ul>
						</div>
					@endif
					<form method="POST" action="{{ route('password.update') }}">
						@csrf
						<input type="hidden" name="token" value="{{ $token }}">
						<div class="mb-3">
							<label for="email" class="form-label">Correo electrónico</label>
							<input id="email" type="email" name="email"
								class="form-control @error('email') is-invalid @enderror"
								value="{{ old('email', $email) }}" required autofocus>
							@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
						<div class="mb-3">
							<label for="password" class="form-label">Nueva contraseña</label>
							<input id="password" type="password" name="password"
								class="form-control @error('password') is-invalid @enderror" required>
							@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
						<div class="mb-3">
							<label for="password_confirmation" class="form-label">Confirmar contraseña</label>
							<input id="password_confirmation" type="password" name="password_confirmation"
								class="form-control @error('password_confirmation') is-invalid @enderror" required>
							@error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
						<button type="submit" class="btn btn-primary w-100">Guardar nueva contraseña</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
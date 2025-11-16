@extends('layouts.app')

@section('title','Verificación 2FA')
@section('page','2fa-challenge')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Verificación 2FA</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <p class="mb-3">Hemos enviado un código de 6 dígitos a tu correo @if(!empty($email))<strong>{{ $email }}</strong>@endif. Ingresa el código para continuar.</p>
                    <form method="POST" action="{{ route('auth.2fa.challenge') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="code" class="form-label">Código</label>
                            <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autofocus>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Verificar</button>
                    </form>
                    <hr>
                    <form method="POST" action="{{ route('auth.2fa.challenge') }}" id="resend2faForm">
                        @csrf
                        <input type="hidden" name="resend" value="1">
                        <button type="button" id="btnResend2fa" class="btn btn-link">Reenviar código</button>
                    </form>
                    <small class="text-muted d-block mt-2">El código expira en 5 minutos.</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

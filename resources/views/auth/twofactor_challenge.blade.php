@extends('layouts.app')

@section('title','Verificación 2FA')

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
<script>
(function(){
    const btn = document.getElementById('btnResend2fa');
    if(btn){
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                const res = await fetch("{{ route('auth.2fa.challenge') }}", {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}, body: JSON.stringify({ resend: 1 })});
                const j = await res.json();
                if(j && j.ok){
                    showNotification('Atención','Código reenviado.', {template:'info'});
                } else {
                    showNotification('Error', j.message || 'No se pudo reenviar.', {template:'error'});
                }
            } catch(e){
                showNotification('Error', 'Error de red', {template:'error'});
            } finally { btn.disabled = false; }
        });
    }
})();
</script>
@endsection

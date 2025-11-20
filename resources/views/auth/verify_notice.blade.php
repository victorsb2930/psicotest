<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifica tu email</title>
  @vite('resources/css/app.css')
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h4 mb-3">Verifica tu email</h1>
            @php
              $msgSuccess = session('success');
              $msgError = session('error');
              $msgInfo = session('info');
              // Ocultar instrucciones y formulario si el mensaje indica que el email ya está verificado
              $alreadyVerified = false;
              foreach ([$msgSuccess, $msgInfo] as $m) {
                if ($m && stripos($m, 'ya está verificado') !== false) { $alreadyVerified = true; break; }
              }
            @endphp
            @if($msgSuccess)
              <div class="alert alert-success">{{ $msgSuccess }}</div>
            @endif
            @if($msgError)
              <div class="alert alert-danger">{{ $msgError }}</div>
            @endif
            @if($msgInfo)
              <div class="alert alert-info">{{ $msgInfo }}</div>
            @endif

            @if(!$alreadyVerified)
              <p>Te hemos enviado un enlace a tu correo. Haz clic en el enlace para activar tu cuenta.</p>
              <hr>
              <form method="POST" action="{{ route('verification.send') }}" class="row gy-2">
                @csrf
                <div class="col-12">
                  <label for="email" class="form-label">Tu email</label>
                  <input type="email" class="form-control" id="email" name="email" placeholder="tu@correo.com" value="{{ old('email', session('pending_verification_email')) }}" required>
                </div>
                <div class="col-12 d-grid">
                  <button type="submit" class="btn btn-primary">Reenviar enlace</button>
                </div>
              </form>
            @else
              <p class="text-muted mb-0">Tu email ya fue verificado. Puedes iniciar sesión.</p>
            @endif
            <div class="mt-3">
              <a href="/welcome" class="small">Volver al inicio de sesión</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

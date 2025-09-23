@extends('layout')
@section('title','Cuenta en revisión')
@section('page','under-review')
@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">
          <i class="bi bi-hourglass-split display-4 text-warning"></i>
          <h1 class="h3 mt-3">Tu cuenta está en revisión</h1>
          <p class="text-muted">Gracias por registrarte como profesional. Nuestro equipo revisará tus documentos a la brevedad. Te notificaremos por correo cuando tu solicitud sea aprobada o si necesitamos más información.</p>
          <div class="d-flex justify-content-center gap-2 mt-3">
            <a class="btn btn-outline-primary" href="/">Volver al inicio</a>
            @auth
            <form method="POST" action="{{ route('logout') }}">@csrf
              <button class="btn btn-outline-secondary">Cerrar sesión</button>
            </form>
            @else
            <a class="btn btn-outline-secondary" href="/welcome">Iniciar sesión</a>
            @endauth
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

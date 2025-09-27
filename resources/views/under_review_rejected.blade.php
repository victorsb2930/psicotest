@extends('layout')
@section('title','Solicitud rechazada')
@section('page', 'under-review')
@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">
          <i class="bi bi-x-circle display-4 text-danger"></i>
          <h1 class="h3 mt-3">Tu solicitud fue rechazada</h1>
          <p class="text-muted">Lamentablemente tu solicitud profesional ha sido rechazada.</p>
          @php
            $flashed = session('rejection_notes');
            $visibleNotes = $flashed ?? ($isOwner ? $application->notes : null);
          @endphp
          @if($visibleNotes)
            <div class="alert alert-warning">Motivo: <strong>{{ $visibleNotes }}</strong></div>
            <p>Si crees que hubo un error, puedes volver a enviar tu solicitud corrigiendo los documentos. Si necesitas ayuda, contacta al equipo de soporte.</p>
          @else
            <p class="text-muted">Para ver los detalles del rechazo debes iniciar sesión con la cuenta vinculada a esta solicitud.</p>
            <a href="/welcome" class="btn btn-primary">Iniciar sesión</a>
          @endif
          <div class="d-flex justify-content-center gap-2 mt-3">
            <a class="btn btn-outline-primary" href="/">Volver al inicio</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

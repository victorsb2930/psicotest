@extends('layout')
@section('title', 'Perfil profesional')
@section('content')
@php
    $requestedBack = request('back');
    $backUrl = '/professionals';
    if (is_string($requestedBack) && $requestedBack !== '') {
        $backUrl = str_starts_with($requestedBack, '/') ? $requestedBack : $backUrl;
    }
@endphp
<div class="container py-4">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        @if(auth()->check() && auth()->id() !== $u->id)
            <a href="/appointments?professional={{ $u->id }}" class="btn btn-primary btn-sm">Agendar cita</a>
        @endif
    </div>
    <div class="row gy-4 align-items-stretch">
        <div class="col-12 col-lg-4">
            <div class="h-100 rounded-4 bg-light-subtle p-4 shadow-sm d-flex flex-column gap-3 text-center text-lg-start">
                <div class="d-flex flex-column align-items-center align-items-lg-start gap-3">
                    <div id="prof-avatar" style="cursor:pointer;">
                        <img id="prof-avatar-img" src="{{ $avatar ?? Vite::asset('resources/images/Avatar-PNG-Image.png') }}" class="rounded-circle shadow" width="150" height="150" alt="avatar">
                    </div>
                    <div>
                        <h3 class="mb-1">{{ $u->name }}</h3>
                        <p class="text-muted mb-0">{{ $u->speciality ?? 'General' }}</p>
                    </div>
                </div>
                <div class="row row-cols-1 g-3">
                    <div class="col">
                        <span class="text-muted text-uppercase small">Ubicación</span>
                        <p class="mb-0 fw-semibold">{{ $u->location ?? 'No especificada' }}</p>
                    </div>
                    <div class="col">
                        <span class="text-muted text-uppercase small">Contacto</span>
                        <p class="mb-0 fw-semibold text-break">{{ $u->email }}</p>
                    </div>
                    @if(!empty($u->phone))
                        <div class="col">
                            <span class="text-muted text-uppercase small">Teléfono</span>
                            <p class="mb-0 fw-semibold">{{ $u->phone }}</p>
                        </div>
                    @endif
                    <div class="col">
                        <span class="text-muted text-uppercase small">Tipos de cita</span>
                        <p class="mb-0 fw-semibold">{{ is_array($u->appointment_types) ? implode(', ', $u->appointment_types) : ($u->appointment_types ?? '-') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column gap-4">
                    <div>
                        <h5 class="text-uppercase text-muted small">Sobre el profesional</h5>
                        <p class="mb-0">{{ $u->description ?? 'Sin descripción disponible.' }}</p>
                    </div>
                    <div>
                        <h6 class="text-uppercase text-muted small">Experiencia</h6>
                        <p class="mb-0">{{ $u->experience ?? 'No especificada.' }}</p>
                    </div>
                    <div>
                        <h6 class="text-uppercase text-muted small">Métodos de atención</h6>
                        <p class="mb-0">{{ $u->session_modes ?? 'Virtual' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- simple modal for image preview -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body text-center p-0">
        <img id="imagePreviewModalImg" src="" style="width:100%; height:auto;" alt="preview">
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const avatar = document.getElementById('prof-avatar');
    const modalImg = document.getElementById('imagePreviewModalImg');
    avatar && avatar.addEventListener('click', function(){
        const src = document.getElementById('prof-avatar-img')?.src || '';
        if(!src) return;
        modalImg.src = src;
        const m = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        m.show();
    });
});
</script>
@endpush

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
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div id="prof-avatar" style="cursor:pointer;">
                        <img id="prof-avatar-img" src="{{ $avatar ?? Vite::asset('resources/images/Avatar-PNG-Image.png') }}" class="rounded-circle" width="140" height="140" alt="avatar">
                    </div>
                    <h4 class="mt-2">{{ $u->name }}</h4>
                    <div class="text-muted">{{ $u->speciality ?? 'General' }}</div>
                    <div class="mt-3">
                        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5>Sobre el profesional</h5>
                    <p>{{ $u->description ?? 'Sin descripción' }}</p>
                    <hr>
                    <p><strong>Email:</strong> {{ $u->email }}</p>
                    <p><strong>Ubicación:</strong> {{ $u->location ?? 'No especificada' }}</p>
                    <p><strong>Tipos de cita:</strong> {{ is_array($u->appointment_types) ? implode(', ', $u->appointment_types) : ($u->appointment_types ?? '-') }}</p>
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

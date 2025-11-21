@extends('layout')
@section('title','Buscar profesionales')
@section('page', 'professionals-search')
@section('content')
<div class="container py-4">
    <meta name="professionals-search-url" content="{{ route('professionals.search') }}">
    <h1>Buscar profesionales</h1>
    <div class="small text-muted mb-3">Haz clic en el promedio (ej. 4.5★) para ver reseñas públicas del profesional.</div>
    <div class="row mb-3">
        <div class="col-md-4">
            <input id="pf_q" class="form-control" placeholder="Buscar por nombre o email">
        </div>
        <div class="col-md-3">
            <input id="pf_speciality" class="form-control" placeholder="Especialidad">
        </div>
        <div class="col-md-2">
            <button id="pf_search" class="btn btn-primary w-100">Buscar</button>
        </div>
    </div>

    <div id="pf_results" class="row gy-3" aria-live="polite">
        <!-- cards injected here -->
    </div>

    <div id="pf_empty" class="text-center text-muted mt-4 d-none">No se encontraron profesionales.</div>
</div>
@endsection
@extends('layout')
@section('title','Buscar profesionales')
@section('page', 'professionals-search')
@section('content')
<div class="container py-4 py-lg-5">
    <meta name="professionals-search-url" content="{{ route('professionals.search') }}">
    <div class="rounded-4 bg-primary text-white p-4 p-lg-5 shadow-sm">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
            <div>
                <p class="text-uppercase small mb-1 text-white-50">Directorio profesional</p>
                <h1 class="h3 h-lg-2 mb-2">Encuentra al especialista ideal</h1>
                <p class="mb-0 text-white-75">Filtra por nombre, correo o especialidad y revisa sus reseñas públicas antes de agendar.</p>
            </div>
            <div class="ms-lg-auto text-lg-end">
                <span class="badge bg-white text-primary fw-semibold px-3 py-2">Haz clic en el promedio (ej. 4.5★) para ver reseñas.</span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" id="pf_filters" onsubmit="return false;" aria-label="Filtros de búsqueda de profesionales">
                <div class="col-12 col-md-6 col-lg-5">
                    <label for="pf_q" class="form-label text-muted small mb-1">Nombre o correo</label>
                    <input id="pf_q" class="form-control form-control-lg" placeholder="Ej. Ana, correo@dominio.com" autocomplete="off">
                </div>
                <div class="col-12 col-md-4 col-lg-4">
                    <label for="pf_speciality" class="form-label text-muted small mb-1">Especialidad</label>
                    <input id="pf_speciality" class="form-control form-control-lg" placeholder="Ej. Psicología clínica" autocomplete="off">
                </div>
                <div class="col-12 col-md-2 col-lg-3 d-grid">
                    <button id="pf_search" class="btn btn-lg btn-primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="pf_status" class="text-center text-muted small py-3 d-none" role="status"></div>

    <div id="pf_results" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mt-2" aria-live="polite" aria-busy="false"></div>

    <div id="pf_empty" class="d-none">
        <div class="text-center py-5">
            <p class="fw-semibold mb-1">No se encontraron profesionales.</p>
            <p class="text-muted mb-0">Prueba ajustando los filtros o usando un término diferente.</p>
        </div>
    </div>
</div>
@endsection

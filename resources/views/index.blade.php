@extends('layout')
@section('title', 'PsicoTest')
@section('page', 'index')
@section('content')
<section class="py-5 hero-section">
	<div class="container">
		<div class="row align-items-center">
			<div class="col-12 col-lg-7">
				<h1 class="display-4 fw-bold text-dark mb-3 text-dark">TU GUÍA EMOCIONAL, DONDE Y CUANDO LO NECESITES</h1>
				<p class="lead text-muted mb-4">Accede a orientación profesional para tu salud mental desde la comodidad de tu hogar. Encontrá especialistas disponibles para terapia online y seguimiento continuo.</p>
			</div>
			<div class="col-12 col-lg-5 text-center mt-4 mt-lg-0">
				<div class="hero-card">
					<!-- Placeholder illustration replaced by app assets if available -->
					<img src="{{ Vite::asset('resources/images/foto_index.jpg') }}" alt="PsicoTest" class="img-fluid" onerror="this.style.display='none'">
					<div class="text-muted small mt-2">Sesiones seguras y privadas · Videollamada integrada · Pago seguro</div>
                    <div class="mt-3">
                        <button id="btn-mental-test" class="btn btn-cta">Realizar test breve</button>
                    </div>
				</div>
			</div>
		</div>
	</div>
</section>

<section id="beneficios" class="py-5">
	<div class="container">
		<h2 class="text-center fw-bold mb-4">Beneficios de PsicoTest</h2>
		<div class="row g-4 justify-content-center">
			<div class="col-12 col-sm-6 col-md-4">
				<x-card class="benefit-card h-100 border-0" title="Acceso inmediato">
					<x-slot name="icon">
						<div class="icon-circle mb-3"><i class="bi bi-clock-fill fs-3"></i></div>
					</x-slot>
					<p class="card-text text-muted mb-0">Conéctate con especialistas certificados en cualquier momento y lugar.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-md-4">
				<x-card class="benefit-card h-100 border-0" title="Atención personalizada">
					<x-slot name="icon">
						<div class="icon-circle mb-3"><i class="bi bi-chat-left-text-fill fs-3"></i></div>
					</x-slot>
					<p class="card-text text-muted mb-0">Recibe acompañamiento y seguimiento adaptado a tus necesidades emocionales.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-md-4">
				<x-card class="benefit-card h-100 border-0" title="Confidencialidad">
					<x-slot name="icon">
						<div class="icon-circle mb-3"><i class="bi bi-shield-lock-fill fs-3"></i></div>
					</x-slot>
					<p class="card-text text-muted mb-0">Tu privacidad es nuestra prioridad en cada interacción.</p>
				</x-card>
			</div>
		</div>
	</div>
</section>

@endsection

@extends('layout')
@section('title', 'Servicios')

@section('content')
@php
	$professionalsStat = isset($professionalsCount) ? number_format($professionalsCount) : '—';
	$sessionsStat = isset($sessionsCount) ? number_format($sessionsCount) : '—';
	$ratingStat = isset($averageRating) && $averageRating > 0 ? number_format($averageRating, 1) : '4.8';
@endphp
<section class="py-5 py-lg-6 bg-light">
	<div class="container">
		<div class="row align-items-center g-4">
			<div class="col-lg-7">
				<p class="text-uppercase small text-muted mb-2">Acerca de PsicoTest</p>
				<h1 class="display-6 fw-semibold mb-3">Tecnología, empatía y especialistas a tu alcance</h1>
				<p class="lead text-muted mb-4">Conectamos a pacientes y profesionales de la salud mental en un espacio seguro, ágil y humano. Nuestra plataforma facilita las citas, la comunicación y el seguimiento terapéutico desde cualquier dispositivo.</p>
				<div class="row g-3">
					<div class="col-sm-4">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body">
								<p class="h3 fw-bold mb-0">{{ $professionalsStat }}</p>
								<p class="text-muted mb-0">Profesionales activos</p>
							</div>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body">
								<p class="h3 fw-bold mb-0">{{ $sessionsStat }}</p>
								<p class="text-muted mb-0">Sesiones gestionadas</p>
							</div>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="card border-0 shadow-sm h-100">
							<div class="card-body">
								<p class="h3 fw-bold mb-0">{{ $ratingStat }}★</p>
								<p class="text-muted mb-0">Satisfacción promedio</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg-5">
				<div class="ratio ratio-4x3 rounded-4 overflow-hidden shadow-lg">
					<img src="{{ Vite::asset('resources/images/about-us-image-2.webp') }}" alt="Profesionales de PsicoTest" class="w-100 h-100 object-fit-cover">
				</div>
			</div>
		</div>
	</div>
</section>

<section class="py-5">
	<div class="container">
		<div class="row g-4">
			<div class="col-lg-6">
				<h2 class="h3 mb-3">Nuestra misión</h2>
				<p class="text-muted mb-0">Facilitamos que cada persona encuentre el acompañamiento emocional adecuado, con herramientas digitales que priorizan la privacidad, la calidad profesional y la accesibilidad económica.</p>
			</div>
			<div class="col-lg-6">
				<h2 class="h3 mb-3">Nuestra visión</h2>
				<p class="text-muted mb-0">Ser la plataforma de referencia en salud mental para Latinoamérica, impulsando comunidades más conscientes y resilientes mediante experiencias digitales cálidas y confiables.</p>
			</div>
		</div>
		<div class="row g-4 mt-1">
			<div class="col-md-4">
				<x-card class="h-100 card-featured" :center="false" :hover="false" :borderless="true">
					<h3 class="h5 mb-2">Cuidado integral</h3>
					<p class="text-muted mb-0">Agenda inteligente, historial clínico seguro y recordatorios automatizados para ambas partes.</p>
				</x-card>
			</div>
			<div class="col-md-4">
				<x-card class="h-100 card-featured" :center="false" :hover="false" :borderless="true">
					<h3 class="h5 mb-2">Profesionales verificados</h3>
					<p class="text-muted mb-0">Cada especialista pasa por un proceso de validación de credenciales y desempeño.</p>
				</x-card>
			</div>
			<div class="col-md-4">
				<x-card class="h-100 card-featured" :center="false" :hover="false" :borderless="true">
					<h3 class="h5 mb-2">Apoyo continuo</h3>
					<p class="text-muted mb-0">Chat seguro, videollamadas y recursos educativos para sostener el proceso terapéutico.</p>
				</x-card>
			</div>
		</div>
	</div>
</section>

<section class="py-5 bg-dark text-white">
	<div class="container">
		<div class="row align-items-center g-4">
			<div class="col-lg-7">
				<h2 class="h3 mb-3">¿Por qué PsicoTest?</h2>
				<ul class="list-unstyled mb-0">
					<li class="mb-3 d-flex gap-3"><i class="bi bi-check-circle text-success"></i><span>Infraestructura en la nube con cifrado extremo para proteger tus datos y sesiones.</span></li>
					<li class="mb-3 d-flex gap-3"><i class="bi bi-check-circle text-success"></i><span>Algoritmos de emparejamiento que sugieren profesionales según tus objetivos terapéuticos.</span></li>
					<li class="mb-0 d-flex gap-3"><i class="bi bi-check-circle text-success"></i><span>Soporte humano 24/7 para responder dudas de pacientes y especialistas.</span></li>
				</ul>
			</div>
			<div class="col-lg-5">
				<div class="bg-white text-dark rounded-4 p-4 shadow-lg">
					<h3 class="h5 mb-3">Lo que dicen nuestros usuarios</h3>
					<figure class="mb-0">
						<blockquote class="blockquote text-muted">“Llegar a mi terapeuta ideal fue más sencillo de lo que imaginé. PsicoTest me acompaña en cada paso.”</blockquote>
						<figcaption class="blockquote-footer mb-0">Carolina M. · Paciente desde 2023</figcaption>
					</figure>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="py-5">
	<div class="container">
		<div class="rounded-4 bg-primary text-white p-4 p-lg-5 d-flex flex-column flex-lg-row gap-4 align-items-lg-center">
			<div>
				<h2 class="h3 mb-2">Listo para transformar tu práctica o cuidar tu bienestar?</h2>
				<p class="mb-0">Únete a nuestra comunidad y descubre herramientas diseñadas para terapeutas y pacientes.</p>
			</div>
			<div class="ms-lg-auto d-flex flex-wrap gap-2">
				<a href="{{ route('register') }}" class="btn btn-light text-primary fw-semibold">Crear cuenta</a>
				<a href="{{ route('contact') }}" class="btn btn-outline-light">Habla con nosotros</a>
			</div>
		</div>
	</div>
</section>
@endsection

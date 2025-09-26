@extends('layout')
@section('title', 'Servicios')
@section('page', 'services')
@section('content')
<section class="py-5">
	<div class="container">
		<div class="row mb-4">
			<div class="col-12 text-center">
				<h2 class="fw-bold text-brand-dark mb-2">Nuestros Servicios</h2>
				<p class="text-muted mb-0">Acompañamiento profesional y cercano, cuando más lo necesitas.</p>
			</div>
		</div>
		<div class="row g-3 g-md-4 justify-content-center">
			<div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex">
				<x-card :center="true" :hover="true" :squareMd="false" :compact="true" class="w-100">
					<img src="{{ Vite::asset('resources/images/3602139.png') }}" alt="Sesiones" class="mb-3 mx-auto d-block" width="72" height="72">
					<h3 class="h5 text-brand-dark mb-2">Sesiones Virtuales</h3>
					<p class="small text-muted mb-0">Conecta con psicólogos certificados mediante videollamadas privadas y seguras desde donde estés.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex">
				<x-card :center="true" :hover="true" :squareMd="false" :compact="true" class="w-100">
					<img src="{{ Vite::asset('resources/images/4682131.png') }}" alt="Test" class="mb-3 mx-auto d-block" width="72" height="72">
					<h3 class="h5 text-brand-dark mb-2">Evaluación Psicológica</h3>
					<p class="small text-muted mb-0">Accede a un test emocional inicial que ayuda a identificar tu nivel de estrés, ansiedad o depresión.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex">
				<x-card :center="true" :hover="true" :squareMd="false" :compact="true" class="w-100">
					<img src="{{ Vite::asset('resources/images/2784445.png') }}" alt="Apoyo 24/7" class="mb-3 mx-auto d-block" width="72" height="72">
					<h3 class="h5 text-brand-dark mb-2">Apoyo 24/7</h3>
					<p class="small text-muted mb-0">Disponibilidad continua para ayudarte cuando más lo necesites, sin importar la hora ni el lugar.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex">
				<x-card :center="true" :hover="true" :squareMd="false" :compact="true" class="w-100">
					<img src="{{ Vite::asset('resources/images/785116.png') }}" alt="Recursos" class="mb-3 mx-auto d-block" width="72" height="72">
					<h3 class="h5 text-brand-dark mb-2">Recursos y Guías</h3>
					<p class="small text-muted mb-0">Biblioteca de artículos, ejercicios y recursos para acompañar tu proceso de bienestar.</p>
				</x-card>
			</div>
		</div>
	</div>
</section>
@endsection
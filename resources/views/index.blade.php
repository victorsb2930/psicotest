@extends('layout')
@section('title', 'PsicoGuia')
@section('page', 'index')
@section('content')

<section class="py-5">
	<div class="container">
		<div class="row justify-content-center text-center">
			<div class="col-12 col-lg-8">
				<h1 class="display-5 fw-bold text-brand-dark mb-3">Tu guía emocional, donde y cuando la necesites</h1>
				<p class="lead mb-0">Accede a orientación profesional para tu salud mental desde la
					comodidad de tu hogar.</p>
			</div>
		</div>
	</div>
</section>

<section id="beneficios" class="py-5 bg-brand-soft">
		<div class="container">
			<h2 class="text-center text-brand fw-bold mb-4">Beneficios de PsicoGuía</h2>
			<div class="row g-4 justify-content-center align-items-stretch">
			<div class="col-12 col-sm-6 col-md-4">
				<x-card :square-md="true" icon="🧠" class="text-center">
					<h5 class="card-title text-brand-dark fw-bold">Acceso inmediato</h5>
					<p class="card-text text-muted mb-0">Conéctate con especialistas certificados en cualquier momento y lugar.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-md-4">
				<x-card :square-md="true" icon="💬" class="text-center">
					<h5 class="card-title text-brand-dark fw-bold">Atención personalizada</h5>
					<p class="card-text text-muted mb-0">Recibe acompañamiento y seguimiento adaptado a tus necesidades emocionales.</p>
				</x-card>
			</div>
			<div class="col-12 col-sm-6 col-md-4">
				<x-card :square-md="true" icon="🔒" class="text-center">
					<h5 class="card-title text-brand-dark fw-bold">Confidencialidad garantizada</h5>
					<p class="card-text text-muted mb-0">Tu privacidad es nuestra prioridad en cada interacción.</p>
				</x-card>
			</div>
		</div>
	</div>
</section>

<section id="testimonios" class="py-5 bg-brand-soft">
		<div class="container">
			<h2 class="text-center text-brand fw-bold mb-4">Testimonios</h2>
			<div class="row g-4 justify-content-center align-items-stretch">
			<div class="col-12 col-md-4">
				<x-card :square-md="true" class="position-relative">
					<div class="position-absolute top-0 start-0 p-3 display-5 opacity-25">❝</div>
					<p class="mb-4 fst-italic text-muted">“Gracias a PsicoGuía pude encontrar un terapeuta que realmente me entiende y me ayuda cada día.”</p>
					<h6 class="text-end text-brand fw-bold mb-0">— Mariana R.</h6>
				</x-card>
			</div>
			<div class="col-12 col-md-4">
				<x-card :square-md="true" class="position-relative">
					<div class="position-absolute top-0 start-0 p-3 display-5 opacity-25">❝</div>
					<p class="mb-4 fst-italic text-muted">“La plataforma es muy fácil de usar y el acompañamiento profesional me ha dado mucha paz mental.”</p>
					<h6 class="text-end text-brand fw-bold mb-0">— José M.</h6>
				</x-card>
			</div>
			<div class="col-12 col-md-4">
				<x-card :square-md="true" class="position-relative">
					<div class="position-absolute top-0 start-0 p-3 display-5 opacity-25">❝</div>
					<p class="mb-4 fst-italic text-muted">“Me encanta la variedad de recursos y la atención personalizada que ofrecen.”</p>
					<h6 class="text-end text-brand fw-bold mb-0">— Carla P.</h6>
				</x-card>
			</div>
		</div>
	</div>
</section>

@endsection
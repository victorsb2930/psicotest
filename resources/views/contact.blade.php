@extends('layout')
@section('title', 'Contacto')
@section('page','contact')
@section('content')
	<section class="py-5">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-9">
					<h1 class="display-6 fw-semibold text-center contact-accent mb-2">Contacto</h1>
					<p class="text-center text-muted mb-4">Estamos aquí para ayudarte. Escríbenos y te responderemos pronto.</p>
					<div class="card contact-card border-0">
						<div class="card-body p-4 p-md-5">
							<div class="row g-4">
								<div class="col-12 col-lg-7">
										<form id="contactForm" action="{{ route('contact.send') }}" method="POST" class="needs-validation" novalidate>
											@csrf
										<div class="mb-3">
											<label for="name" class="form-label">Nombre</label>
											<input type="text" class="form-control" id="name" name="name" placeholder="Tu nombre" required>
										</div>
										<div class="mb-3">
											<label for="email" class="form-label">Correo electrónico</label>
											<input type="email" class="form-control" id="email" name="email" placeholder="tu@correo.com" required>
										</div>
										<div class="mb-3">
											<label for="subject" class="form-label">Asunto</label>
											<input type="text" class="form-control" id="subject" name="subject" placeholder="Motivo del mensaje" required>
										</div>
										<div class="mb-3">
											<label for="message" class="form-label">Mensaje</label>
											<textarea class="form-control" id="message" name="message" rows="5" placeholder="Escribe tu mensaje aquí..." required></textarea>
										</div>
										<div class="d-grid d-sm-flex gap-2">
											<button type="submit" class="btn btn-brand px-4">Enviar mensaje</button>
											<a href="mailto:contacto@psicoguia.com" class="btn btn-outline-secondary">Escribir por correo</a>
										</div>
									</form>
								</div>
								<div class="col-12 col-lg-5">
									<x-card :center="false" :hover="false" :compact="true" :borderless="true" class="bg-brand-soft h-100">
										<h2 class="h5 fw-bold mb-3 text-brand">Información de contacto</h2>
										<ul class="list-unstyled small mb-4 text-600">
											<li class="mb-2"><strong class="text-dark">Email:</strong> contacto@psicoguia.com</li>
											<li class="mb-2"><strong class="text-dark">Teléfono:</strong> +1 809 555 1234</li>
											<li><strong class="text-dark">Horario:</strong> Lunes a Viernes, 9am - 6pm</li>
										</ul>
										<h3 class="h5 fw-bold text-brand">Síguenos</h3>
										<div class="d-flex flex-column gap-2">
											<a class="link-secondary text-decoration-underline" href="#" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
											<a class="link-secondary text-decoration-underline" href="https://www.instagram.com/paulfrankis__1/" target="_blank" rel="noopener"><i class="bi bi-instagram"></i> Instagram</a>
											<a class="link-secondary text-decoration-underline" href="#" target="_blank" rel="noopener"><i class="bi bi-twitter"></i> Twitter</a>
										</div>
									</x-card>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
@endsection
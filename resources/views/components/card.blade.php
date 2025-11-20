<style>
	/* Card helpers */
	.card-compact { padding: 1rem !important; }
	.card-anim-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; will-change: transform; }
	.card-anim-lift:hover { transform: translateY(-6px); box-shadow: 0 10px 20px rgba(245,129,152,.35) !important; }
	.card-square-md { width: 100%; }
	.card-square-md .card-body { display: flex; flex-direction: column; justify-content: center; align-items: center; }
	@media (min-width: 768px) { /* md */
		.card-square-md { width: 260px; aspect-ratio: 1 / 1; }
	}

	@media (prefers-reduced-motion: reduce) {
		.card-anim-lift { transition: none; }
		.card-anim-lift:hover { transform: none; }
	}
</style>

@props([
	'title' => null,
	'titleRight' => null,
	'subtitle' => null,
	'icon' => null,
	'footer' => null,
	'center' => true,
	'hover' => true,
	'squareMd' => false,
	'compact' => true,
	'borderless' => false,
	'class' => '',
	'height' => '100',
	'width' => '100',
])
@php
	$classes = trim("card h-$height w-$width shadow-brand mx-auto ". ($borderless ? 'border-0 ' : '') . ($center ? 'text-center ' : '') . ($hover ? 'card-anim-lift ' : '') . ($squareMd ? 'card-square-md ' : '') . $class);
	$bodyClasses = trim('card-body ' . ($compact ? 'card-compact ' : ''));
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
	<div class="{{ $bodyClasses }}">
		@if($icon)
			<div class="display-4 mb-2">{!! $icon !!}</div>
		@endif

		<div class="d-flex justify-content-between align-items-center">
		@if($title)
			<h5 class="card-title fw-bold">{{ $title }} </h5>
		@endif

		@if($titleRight)
            <span class="card-title-title-right float-end">{!! $titleRight !!}</span>
        @endif
		</div>

		@if($subtitle)
			<p class="card-text text-muted">{{ $subtitle }}</p>
		@endif

		{{ $slot }}
	</div>

	@if($footer)
		<div class="card-footer">{!! $footer !!}</div>
	@endif
</div>
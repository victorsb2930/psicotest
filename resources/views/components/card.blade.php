<style>
	/* Card helpers (responsive + accessible) */
	.card-compact { padding: .75rem; }
	@media (min-width: 768px) { .card-compact { padding: 1rem; } }

	.card-anim-lift { transition: transform .18s ease, box-shadow .18s ease; will-change: transform; }
	.card-anim-lift:hover { transform: translateY(-6px); box-shadow: 0 10px 20px rgba(245,129,152,.18) !important; }

	.card-square-md { width: 100%; }
	.card-square-md .card-body { display: flex; flex-direction: column; justify-content: center; align-items: center; }
	@media (min-width: 768px) { /* md */
		.card-square-md { max-width: 320px; aspect-ratio: 1 / 1; }
	}

	.card-featured { border: 1px solid rgba(245,129,152,.15); box-shadow: 0 6px 20px rgba(245,129,152,.06); }

	/* Plan specific helpers */
	.plan-active { border: 1.5px solid rgba(25, 135, 84, 0.12); box-shadow: 0 8px 28px rgba(25,135,84,0.06); }
	.plan-discount-badge { font-size: .775rem; margin-right: .35rem; }
	.plan-popular-badge { background-color: var(--brand-500); color: #06283D; }
	.price-large { font-size: 1.6rem; font-weight: 700; }
	.btn-plan-cta:focus { outline: 3px solid rgba(13,110,253,0.15); outline-offset: 2px; }

	/* Reduce motion preference */
	@media (prefers-reduced-motion: reduce) {
		.card-anim-lift { transition: none; }
		.card-anim-lift:hover { transform: none; }
	}
</style>

@props([
	'title' => null,
	'titleRight' => null,
	'subtitle' => null,
	// Use a named slot `icon` instead of passing raw HTML via prop to avoid parsing issues
	'footer' => null,
	'center' => true,
	'hover' => true,
	'squareMd' => false,
	'compact' => true,
	'borderless' => false,
	'class' => '',
	'height' => null,
	'width' => null,
])
@php
	// Build classes
	$baseClasses = 'card shadow-brand mx-auto ';
	if ($borderless) { $baseClasses .= 'border-0 '; }
	if ($center) { $baseClasses .= 'text-center '; }
	if ($hover) { $baseClasses .= 'card-anim-lift '; }
	if ($squareMd) { $baseClasses .= 'card-square-md '; }
	$classes = trim($baseClasses . ' ' . $class);

	$bodyClasses = trim('card-body ' . ($compact ? 'card-compact ' : ''));

	// Optional inline sizing (px). If not provided, avoid forcing fixed sizes so cards stay fluid.
	$styleAttr = '';
	if (!empty($height)) { $styleAttr .= 'height: '.$height.(is_numeric($height) ? 'px;' : ';'); }
	if (!empty($width)) { $styleAttr .= 'width: '.$width.(is_numeric($width) ? 'px;' : ';'); }
@endphp

<div {{ $attributes->merge(['class' => $classes, 'style' => $styleAttr]) }}>
	<div class="{{ $bodyClasses }}">
		{{-- Icon slot: use <x-slot name="icon"> to provide HTML icon markup from the caller --}}
		@isset($icon)
			<div class="display-4 mb-2">{!! $icon !!}</div>
		@endisset

		<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 w-100">
			@if($title)
				<h5 class="card-title fw-bold mb-0">{{ $title }}</h5>
			@endif

			@if($titleRight)
				<span class="card-title-title-right ms-md-2">{!! $titleRight !!}</span>
			@endif
		</div>

		@if($subtitle)
			<p class="card-text text-muted mb-2">{{ $subtitle }}</p>
		@endif

		{{ $slot }}
	</div>

	@if($footer)
		<div class="card-footer">{!! $footer !!}</div>
	@endif
</div>

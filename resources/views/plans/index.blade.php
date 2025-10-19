@extends('layout')
@section('title','Planes de membresía')
@section('page','plans')
@section('content')
@php
	use App\Models\Plan;
	// Exclude zero-price/free plans from the purchase list (free plan should be default at signup)
	$plans = Plan::query()->where('active', true)->where('price_cents','>',0)->orderBy('price_cents')->get();
	$user = auth()->user();
	$activeSub = null;
	$activePlanId = null;
	$activePriceCents = 0;
	if ($user) {
		// Prefer explicit active status, but fall back to any subscription that hasn't ended yet
		// Include subscriptions with null ends_at (indefinite) as active
		$activeSub = $user->subscriptions()->with('plan')->where(function($q){
			$q->where('status','active')
			  ->orWhereNull('ends_at')
			  ->orWhere('ends_at','>', now());
		})->orderBy('ends_at','desc')->first();
		if ($activeSub && $activeSub->plan) {
			$activePlanId = $activeSub->plan->id;
			$activePriceCents = $activeSub->plan->price_cents ?? 0;
		}
	}

	function formatPrice($plan) {
		if (!$plan) return '';
		if ((int)($plan->price_cents ?? 0) === 0) return 'Gratis';
		$cents = $plan->price_cents;
		$currency = $plan->currency ?: 'USD';
		return sprintf('%s%.2f/%s', ($currency === 'USD' ? '$' : ''), $cents/100, $plan->interval ?: 'mes');
	}
@endphp

<div class="container py-4" id="plans-root" data-active-price="{{ $activePriceCents }}" data-active-plan-id="{{ $activePlanId }}">
	<h1>Planes de membresía</h1>
	<p class="text-muted">Elige el plan que mejor se adapte a tus necesidades. El pago está simulado por ahora.</p>

	<div class="row g-3 mt-3 justify-content-center">

		@foreach($plans as $p)
				@php
					// Use the resolved activePlanId (more reliable) to determine current plan
					$isActive = $activePlanId && ((int)$activePlanId === (int)$p->id);
				$label = $isActive ? 'Activo' : ((int)($p->price_cents ?? 0) === 0 ? 'Seleccionar' : 'Contratar');
				// Format features to a human-friendly subtitle
				$subtitleVar = '';
				if (!empty($p->features) && is_array($p->features)) {
					$parts = [];
					if (array_key_exists('chats_per_month', $p->features)) {
						$ch = $p->features['chats_per_month'];
						$parts[] = 'Chats/mes: ' . (is_null($ch) ? 'Ilimitado' : (string)$ch);
					}
					if (array_key_exists('appointments_included_per_month', $p->features)) {
						$ap = $p->features['appointments_included_per_month'];
						$parts[] = 'Turnos incluidos/mes: ' . (is_null($ap) ? 'Ilimitado' : (string)$ap);
					}
					if (array_key_exists('discount_percent', $p->features)) {
						$ds = $p->features['discount_percent'];
						$parts[] = 'Descuento: ' . (is_null($ds) ? '0%' : (string)$ds . '%');
					}
					$subtitleVar = implode(' · ', $parts);
				} else {
					$subtitleVar = '';
				}
				$iconHtml = '<i class="bi bi-award"></i>';
			@endphp
			<div class="col-12 col-md-6 col-lg-4 d-flex justify-content-center">
				<x-card :title="$p->name" :subtitle="$subtitleVar" :icon="$iconHtml" class="w-100" :squareMd="true" :compact="true" :hover="true">
					<div class="mt-2 mb-3">
						<div class="fw-semibold display-6">{!! formatPrice($p) !!}</div>
					</div>
					<div class="d-flex justify-content-between align-items-center w-100">
						<small class="text-muted">Beneficios clave incluidos</small>
						@if($isActive)
							<span class="badge bg-success" aria-live="polite">Tu plan actual</span>
						@else
							<button class="btn btn-primary plan-cta" data-plan="{{ $p->key }}" data-title="{{ $p->name }}" data-desc="{{ $subtitleVar }}" data-price="{{ $p->price_cents ?? 0 }}" data-key="{{ $p->key }}">{{ $label }}</button>
						@endif
					</div>
				</x-card>
			</div>
		@endforeach
	</div>
</div>
@endsection

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

@php
	// Filter plans by visibility: if plan.features.visible_roles exists and is non-empty,
	// show only to users having one of those roles. If not present, default to visible to everyone.
	$plans = $plans->filter(function($plan) use ($user) {
		$visible = $plan->features['visible_roles'] ?? null;
		if (is_array($visible) && count($visible) > 0) {
			if (!$user) return false;
			$userRoleIds = $user->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray();
			return (count(array_intersect($visible, $userRoleIds)) > 0);
		}
		// default: visible to everyone when no explicit visible_roles configured
		return true;
	})->values();

	$activeEndsAtIso = $activeSub && $activeSub->ends_at ? $activeSub->ends_at->toIso8601String() : '';
	$activeEndsHuman = $activeSub && $activeSub->ends_at ? $activeSub->ends_at->diffForHumans() : ($activeSub ? 'Indefinido' : 'Ninguno');
@endphp
@can('adminarea')
	@php $rolesList = \Spatie\Permission\Models\Role::all()->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->toArray(); @endphp
	<div class="container py-4" id="plans-root" data-active-price="{{ $activePriceCents }}" data-active-plan-id="{{ $activePlanId }}" data-active-plan-key="{{ $activeSub && $activeSub->plan ? $activeSub->plan->key : '' }}" data-active-ends-at="{{ $activeEndsAtIso }}" data-roles='{{ e(json_encode($rolesList)) }}'>
@else
	<div class="container py-4" id="plans-root" data-active-price="{{ $activePriceCents }}" data-active-plan-id="{{ $activePlanId }}" data-active-plan-key="{{ $activeSub && $activeSub->plan ? $activeSub->plan->key : '' }}" data-active-ends-at="{{ $activeEndsAtIso }}">
@endcan
	<h1>Planes de membresía</h1>
	<p class="text-muted">Elige el plan que mejor se adapte a tus necesidades. El pago está simulado por ahora.</p>

	@if($activeSub && $activeSub->plan)
		<div class="mb-3">
			<div class="alert alert-info d-flex justify-content-between align-items-center">
				<div>
					<strong>Tu plan actual:</strong>
					<span class="ms-1">{{ $activeSub->plan->name }}</span>
					<small class="text-muted d-block">Vence: {{ $activeEndsHuman }} @if($activeEndsAtIso) ({{ \Carbon\Carbon::parse($activeEndsAtIso)->toDateString() }})@endif</small>
				</div>
				<div class="text-end small text-muted">
					<span>Precio actual: <strong>{!! formatPrice($activeSub->plan) !!}</strong></span>
				</div>
			</div>
		</div>
	@endif

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
						$parts[] = 'Citas incluidas/mes: ' . (is_null($ap) ? 'Ilimitado' : (string)$ap);
					}
					$subtitleVar = implode(' · ', $parts);
				} else {
					$subtitleVar = '';
				}
				$iconHtml = '<i class="bi bi-award"></i>';
			@endphp
			@php
				$cardClasses = 'w-100';
				if ($loop->iteration === ceil($loop->count/2)) { $cardClasses .= ' card-featured position-relative'; }
				if ($isActive) { $cardClasses .= ' plan-active'; }
			@endphp
			<div class="col-12 col-md-6 col-lg-4 d-flex justify-content-center" data-plan-key="{{ $p->key }}" data-plan-id="{{ $p->id }}" data-plan-title="{{ $p->name }}" data-plan-price="{{ $p->price_cents ?? 0 }}" data-plan-desc="{{ $subtitleVar }}" data-plan-discount="{{ isset($p->features['discount_percent']) ? $p->features['discount_percent'] : 0 }}" data-plan-visible-roles='{{ e(json_encode($p->features['visible_roles'] ?? [])) }}' data-plan-multi-discounts='{{ e(json_encode($p->features['multi_month_discounts'] ?? [])) }}'>
				<x-card :title="$p->name" :subtitle="$subtitleVar" :icon="$iconHtml" class="{{ $cardClasses }}" :squareMd="true" :compact="true" :hover="true">
					@if(($p->features['popular'] ?? false) === true)
						<span class="badge plan-popular-badge position-absolute top-0 end-0 m-2">Más popular</span>
					@elseif($loop->iteration === ceil($loop->count/2))
						<span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">Recomendado</span>
					@endif
					<div class="mt-2 mb-3 text-center">
						<div class="fw-semibold display-6 mb-0 price-large" aria-label="Precio del plan">{!! formatPrice($p) !!}</div>
						@if(!empty($p->features['multi_month_discounts']) && is_array($p->features['multi_month_discounts']))
							<div class="mt-2">
								@foreach($p->features['multi_month_discounts'] as $md)
									@php
										$m = $md['months'] ?? ($md['min'] ?? null);
										$pc = $md['percent'] ?? ($md['p'] ?? null);
									@endphp
									@if($m && $pc !== null)
										<span class="badge bg-info text-dark plan-discount-badge">-{{ $pc }}% ({{ $m }}+ meses)</span>
									@endif
								@endforeach
							</div>
						@endif
						@can('adminarea')
							<div class="mt-2">
								<button type="button" class="btn btn-sm btn-outline-secondary plan-edit" data-plan-id="{{ $p->id }}" data-plan-key="{{ $p->key }}" aria-label="Editar precio">Editar precio</button>
							</div>
						@endcan
					</div>
					<div class="d-flex flex-column flex-md-row justify-content-between align-items-center w-100 gap-2">
						<small class="text-muted">Beneficios clave incluidos</small>
						@php $currentUser = auth()->user(); @endphp
						@if($currentUser && $currentUser->can('adminarea'))
							{{-- Admins/editors: only allow editing price/visibility, do not allow contracting here --}}
							<span class="text-muted small">Modo administración: no puedes contratar desde aquí</span>
						@else
							@if($isActive)
								<span class="badge bg-success" aria-live="polite">Tu plan actual</span>
							@else
								<button class="btn btn-primary btn-plan-cta plan-cta w-100 w-md-auto py-2" aria-label="Contratar {{ $p->name }}" data-plan="{{ $p->key }}" data-title="{{ $p->name }}" data-desc="{{ $subtitleVar }}" data-price="{{ $p->price_cents ?? 0 }}" data-key="{{ $p->key }}">{{ $label }}</button>
							@endif
						@endif
					</div>
				</x-card>
			</div>
		@endforeach
	</div>
</div>
@endsection

@props(['item','badge'=>null])
@php
  $routeName = $item->route_name ?? null;
  $href = method_exists($item,'resolvedUrl') ? $item->resolvedUrl() : ($item->href ?? '#');
  $active = $routeName ? request()->routeIs($routeName) : false;
  $icon = $item->icon_class ?? null;
@endphp
<li class="nav-item">
  <a href="{{ $href }}" class="nav-link px-0 d-flex align-items-center {{ $active ? 'active' : '' }}">
    <span>@if($icon)<i class="{{ $icon }} me-2"></i>@endif{{ $item->label }}</span>
    @if($badge && $badge>0)
      <span class="badge text-bg-light text-dark ms-2">{{ $badge }}</span>
    @endif
  </a>
</li>

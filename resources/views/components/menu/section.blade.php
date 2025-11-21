@props(['items','showDivider'=>false])
@if($showDivider)
  <hr class="sidebar-divider my-0">
@endif
@foreach($items as $it)
  <x-menu.item :item="$it" :badge="$badgeMap[$it->route_name] ?? null" />
@endforeach
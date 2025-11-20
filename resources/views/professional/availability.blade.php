@extends('layout')
@section('title','Disponibilidad')
@section('page','professional-availability')
@section('content')
<div class="container py-4">
	<h1 class="mb-4 d-flex align-items-center gap-3">
		<span>Disponibilidad semanal</span>
		<span class="ms-auto"><button id="btn-add-slot" class="btn btn-sm btn-primary">Añadir rango</button></span>
	</h1>
	<div class="alert alert-info small">Define rangos horarios por día y excepciones (bloqueos o disponibilidad adicional).</div>
	<div id="weeklySlots" class="mb-4">
		<table class="table table-sm align-middle">
			<thead><tr><th scope="col">Día</th><th scope="col">Inicio</th><th scope="col">Fin</th><th scope="col" class="text-center">Acciones</th></tr></thead>
			<tbody>
			@foreach($weekly as $w)
			@php $start = substr($w->start_time,0,5); $end = substr($w->end_time,0,5); @endphp
			<tr data-id="{{ $w->id }}" data-day="{{ $w->day_of_week }}" data-start="{{ $start }}" data-end="{{ $end }}">
				<td>{{ ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'][$w->day_of_week] }}</td>
				<td>{{ $start }}</td>
				<td>{{ $end }}</td>
				<td class="text-nowrap text-center">
					<button class="btn btn-sm btn-outline-secondary" data-action="edit-slot" data-id="{{ $w->id }}">Editar</button>
					<button class="btn btn-sm btn-outline-primary" data-action="dup-slot" data-id="{{ $w->id }}" title="Duplicar">Duplicar</button>
					<button class="btn btn-sm btn-outline-danger" data-action="del-slot" data-id="{{ $w->id }}">Eliminar</button>
				</td>
			</tr>
			@endforeach
			@if($weekly->isEmpty())
			<tr><td colspan="4" class="text-muted">Sin rangos definidos</td></tr>
			@endif
			</tbody>
		</table>
	</div>
	<h2 class="mb-3 d-flex align-items-center gap-2"><span>Excepciones</span><button id="btn-add-exc" class="btn btn-sm btn-secondary ms-auto">Añadir excepción</button></h2>
	<div id="exceptionsList">
		<table class="table table-sm align-middle">
			<thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Inicio</th><th scope="col">Fin</th><th scope="col">Razón</th><th scope="col" class="text-center">Acciones</th></tr></thead>
			<tbody>
			@foreach($exceptions as $e)
			@php $st = $e->start_time? substr($e->start_time,0,5): ''; $et = $e->end_time? substr($e->end_time,0,5): ''; @endphp
			<tr data-id="{{ $e->id }}" data-date="{{ $e->date->format('Y-m-d') }}" data-status="{{ $e->status }}" data-start="{{ $st }}" data-end="{{ $et }}" data-reason="{{ $e->reason }}">
				<td>{{ $e->date->format('d/m/Y') }}</td>
				<td>{{ $e->status === 'blocked' ? 'Bloqueado' : 'Disponible extra' }}</td>
				<td>{{ $st ?: '-' }}</td>
				<td>{{ $et ?: '-' }}</td>
				<td>{{ $e->reason ?? '-' }}</td>
				<td class="text-nowrap text-center">
					<button class="btn btn-sm btn-outline-secondary" data-action="edit-exc" data-id="{{ $e->id }}">Editar</button>
					<button class="btn btn-sm btn-outline-primary" data-action="dup-exc" data-id="{{ $e->id }}" title="Duplicar">Duplicar</button>
					<button class="btn btn-sm btn-outline-danger" data-action="del-exc" data-id="{{ $e->id }}">Eliminar</button>
				</td>
			</tr>
			@endforeach
			@if($exceptions->isEmpty())
			<tr><td colspan="6" class="text-muted">Sin excepciones recientes</td></tr>
			@endif
			</tbody>
		</table>
	</div>
</div>
@endsection

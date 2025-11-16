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
			<thead><tr><th>Día</th><th>Inicio</th><th>Fin</th><th></th></tr></thead>
			<tbody>
			@foreach($weekly as $w)
			<tr data-id="{{ $w->id }}">
				<td>{{ ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'][$w->day_of_week] }}</td>
				<td>{{ substr($w->start_time,0,5) }}</td>
				<td>{{ substr($w->end_time,0,5) }}</td>
				<td><button class="btn btn-sm btn-outline-danger btn-del-slot" data-id="{{ $w->id }}">Eliminar</button></td>
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
		<table class="table table-sm">
			<thead><tr><th>Fecha</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Razón</th><th></th></tr></thead>
			<tbody>
			@foreach($exceptions as $e)
			<tr data-id="{{ $e->id }}">
				<td>{{ $e->date->format('Y-m-d') }}</td>
				<td>{{ $e->status === 'blocked' ? 'Bloqueado' : 'Disponible extra' }}</td>
				<td>{{ $e->start_time? substr($e->start_time,0,5): '-' }}</td>
				<td>{{ $e->end_time? substr($e->end_time,0,5): '-' }}</td>
				<td>{{ $e->reason ?? '-' }}</td>
				<td><button class="btn btn-sm btn-outline-danger btn-del-exc" data-id="{{ $e->id }}">Eliminar</button></td>
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

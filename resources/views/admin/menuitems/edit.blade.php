@extends('layouts.app')

@section('content')
<div class="container py-3">
  <h1 class="h4 mb-3">Editar elemento de menú</h1>
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  @include('admin.menuitems._form')
</div>
@endsection

@extends('layout')

@section('title','Notificaciones')
@section('page', 'notifications')
@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Notificaciones</h3>
                <form method="POST" action="{{ route('notifications.markread') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">Marcar todo como leído</button>
                </form>
            </div>

            <div class="list-group">
                @forelse($notifications as $n)
                    @php $data = is_array($n->data) ? $n->data : (array)$n->data; @endphp
                    <a href="{{ $data['link'] ?? ($data['url'] ?? '#') }}" class="list-group-item list-group-item-action {{ $n->read_at ? 'bg-light' : '' }}">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 small">{{ $data['title'] ?? $data['message'] ?? Str::limit(json_encode($data, JSON_UNESCAPED_UNICODE), 60) }}</h5>
                            <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                        </div>
                        @if(!empty($data['body'] ?? ''))
                            <p class="mb-1 small text-muted">{{ $data['body'] }}</p>
                        @endif
                    </a>
                @empty
                    <div class="p-4 text-center text-muted">No hay notificaciones.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

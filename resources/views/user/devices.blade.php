@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h3>Dispositivos conectados</h3>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>IP</th>
                <th>User-Agent</th>
                <th>Última actividad</th>
                <th>Revocado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($devices as $d)
            <tr>
                <td>{{ $d->name ?? 'Sin nombre' }}</td>
                <td>{{ $d->ip_address ?? '-' }}</td>
                <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;">{{ $d->user_agent ? Str::limit($d->user_agent, 200) : '-' }}</td>
                <td>{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : '-' }}</td>
                <td>{{ $d->revoked_at ? 'Sí (' . $d->revoked_at->diffForHumans() . ')' : 'No' }}</td>
                <td>
                    @if(!$d->revoked_at)
                    <form method="POST" action="{{ route('user.devices.revoke', ['device' => $d->id]) }}">
                        @csrf
                        <button class="btn btn-sm btn-danger">Revocar</button>
                    </form>
                    @else
                        <span class="text-muted">Revocado</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <form method="POST" action="{{ route('user.devices.revoke_all') }}">
        @csrf
        <button class="btn btn-warning">Revocar todos los dispositivos</button>
    </form>
    <p class="text-muted">Puedes revocar dispositivos si detectas actividad sospechosa.</p>
</div>
@endsection

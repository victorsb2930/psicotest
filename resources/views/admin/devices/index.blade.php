@extends('layouts.app')

@section('title','Dispositivos - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Dispositivos registrados</h3>
        <a href="{{ route('adminarea') }}" class="btn btn-sm btn-outline-secondary">Volver al panel</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Usuario</th>
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
                <td>
                    @if($d->user)
                        <div><strong>{{ $d->user->name }}</strong></div>
                        <div class="small text-muted">{{ $d->user->email }}</div>
                    @else
                        <span class="text-muted">(usuario eliminado)</span>
                    @endif
                </td>
                <td>{{ $d->display_name ?? 'Sin nombre' }}</td>
                <td>{{ $d->ip_address ?? '-' }}</td>
                <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;">{{ $d->user_agent ? Str::limit($d->user_agent, 200) : '-' }}</td>
                <td>
                    @php
                        $ls = $d->last_seen_at ?? null;
                        $lastSeenHuman = '-';
                        try {
                            if ($ls instanceof \Illuminate\Support\Carbon || $ls instanceof \DateTimeInterface) {
                                $lastSeenHuman = $ls->diffForHumans();
                            } elseif (!empty($ls)) {
                                $lastSeenHuman = \Illuminate\Support\Carbon::parse($ls)->diffForHumans();
                            }
                        } catch (\Throwable $_) { $lastSeenHuman = '-'; }
                    @endphp
                    {{ $lastSeenHuman }}
                </td>
                <td>{{ $d->revoked_at ? 'Sí (' . $d->revoked_at->diffForHumans() . ')' : 'No' }}</td>
                <td>
                    @if(!$d->revoked_at)
                        <form method="POST" action="{{ route('admin.devices.revoke', ['device' => $d->id]) }}" class="d-inline-block">
                            @csrf
                            <button class="btn btn-sm btn-danger">Revocar</button>
                        </form>
                    @else
                        <span class="text-muted">Revocado</span>
                    @endif
                    @if($d->user)
                        <form method="POST" action="{{ route('admin.devices.revoke_user_all', ['user' => $d->user->id]) }}" class="d-inline-block ms-2">
                            @csrf
                            <button class="btn btn-sm btn-warning">Revocar todos de usuario</button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endsection

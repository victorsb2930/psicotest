@extends('layout')

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
                <td>
                    @php
                        $rev = $d->revoked_at ?? null;
                        $revHuman = 'No';
                        try {
                            if ($rev) {
                                if ($rev instanceof \Illuminate\Support\Carbon || $rev instanceof \DateTimeInterface) {
                                    $revHuman = 'Sí (' . $rev->diffForHumans() . ')';
                                } else {
                                    $revHuman = 'Sí (' . \Illuminate\Support\Carbon::parse($rev)->diffForHumans() . ')';
                                }
                            }
                        } catch (\Throwable $_) { $revHuman = 'Sí'; }
                    @endphp
                    {{ $revHuman }}
                </td>
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

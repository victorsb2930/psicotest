@component('mail::message')
# Hola {{ $recipientName }},

Registramos un pago a tu favor.

@component('mail::panel')
- **Monto:** {{ $amountFormatted }} {{ strtoupper($payment->currency ?? 'USD') }}
- **Tipo:** {{ ucfirst($payment->type ?? 'payout') }}
- **Citas incluidas:** {{ $meta['appointments_count'] ?? '—' }}
- **Periodo:** {{ ($meta['period'] ?? 'custom') === 'month' ? 'Mes en curso' : (($meta['period'] ?? 'custom') === 'total' ? 'Acumulado' : ucfirst($meta['period'] ?? 'Personalizado')) }}
@if (!empty($meta['rate']))
- **Tarifa por cita:** {{ $meta['rate'] }}
@endif
@endcomponent

@if (!empty($meta['notes']))
> {{ $meta['notes'] }}
@endif

Puedes revisar el detalle en tu panel de profesionales dentro de **Historial de Pagos**.

Gracias por acompañarnos,
{{ config('app.name') }}
@endcomponent

<div style="font-family:system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#222;">
    <h2>Factura</h2>
    <p>Hola {{ $user->name }},</p>
    <p>Gracias por contratar el plan <strong>{{ $plan->name }}</strong>. A continuación se muestran los detalles de la suscripción simulada y el cargo:</p>

    <ul>
        <li>Plan: {{ $plan->name }} ({{ $plan->key }})</li>
        <li>Monto: {{ number_format(($payment->amount_cents ?? 0)/100, 2) }} {{ $payment->currency }}</li>
        <li>Estado del pago: {{ $payment->status }}</li>
        <li>Inicia: {{ \Carbon\Carbon::parse($subscription->starts_at)->format('d/m/Y H:i:s') }}</li>
        <li>Finaliza: {{ \Carbon\Carbon::parse($subscription->ends_at)->format('d/m/Y H:i:s') }}</li>
    </ul>

    <p>Este es un comprobante simulado generado por psicoguia.</p>
</div>

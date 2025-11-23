<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Factura - {{ $plan->name }}</title>
  <style>
    body { font-family: DejaVu Sans, Roboto, Arial, sans-serif; color:#222; }
    .header { text-align:center; margin-bottom:20px }
    .items { width:100%; border-collapse: collapse; }
    .items th, .items td { border:1px solid #ddd; padding:8px; }
    .total { text-align:right; font-weight:700; }
  </style>
</head>
<body>
  <div class="header">
    <h2>Factura</h2>
    <div>PsicoGuia</div>
  </div>
  <p>Cliente: {{ $user->name }} &lt;{{ $user->email }}&gt;</p>
  <p>Plan: {{ $plan->name }} ({{ $plan->key }})</p>
  <table class="items">
    <thead>
      <tr><th>Concepto</th><th>Precio</th></tr>
    </thead>
    <tbody>
      <tr><td>Suscripción (1 mes)</td><td>{{ number_format(($payment->amount_cents ?? 0)/100,2) }} {{ $payment->currency }}</td></tr>
    </tbody>
    <tfoot>
      <tr><td class="total">Total</td><td class="total">{{ number_format(($payment->amount_cents ?? 0)/100,2) }} {{ $payment->currency }}</td></tr>
    </tfoot>
  </table>
  <p>Fecha: {{ now() }}</p>
</body>
</html>

<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfessionalPaymentsController extends Controller
{
    private const MAX_SIMULATED_PAYOUT_CENTS = 5000000; // $50,000.00

    public function index(Request $request)
    {
        $user = $this->resolveProfessional($request);

        $query = Payment::with(['user','subscription'])
            ->where('recipient_user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            try { $from = Carbon::parse($request->query('from')); $query->where('created_at', '>=', $from); } catch (\Throwable $_) {}
        }
        if ($request->filled('to')) {
            try { $to = Carbon::parse($request->query('to'))->endOfDay(); $query->where('created_at', '<=', $to); } catch (\Throwable $_) {}
        }

        $payments = $query->orderBy('created_at','desc')->paginate(30)->withQueryString();
        $stats = $this->payoutStats($user->id);

        return view('professional.payments_history', [
            'payments' => $payments,
            'filters' => [ 'status' => $request->query('status',''), 'from' => $request->query('from',''), 'to' => $request->query('to','') ],
            'payoutStats' => $stats,
            'maxPayoutCents' => self::MAX_SIMULATED_PAYOUT_CENTS,
        ]);
    }

    public function export(Request $request)
    {
        $user = $this->resolveProfessional($request);
        $format = strtolower(trim($request->query('format','csv')));
        if (!in_array($format, ['csv','xlsx'], true)) $format = 'csv';

        $query = Payment::with(['user','subscription'])->where('recipient_user_id', $user->id);
        if ($request->filled('status')) $query->where('status', $request->query('status'));
        if ($request->filled('from')) { try { $from = Carbon::parse($request->query('from')); $query->where('created_at','>=',$from); } catch (\Throwable $_) {} }
        if ($request->filled('to')) { try { $to = Carbon::parse($request->query('to'))->endOfDay(); $query->where('created_at','<=',$to); } catch (\Throwable $_) {} }

        $filename = 'pagos_'.$user->id.'_'.now()->format('Ymd_His').'.'.$format;
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function() use ($query) {
            $out = fopen('php://output','w');
            // BOM for Excel
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['ID','FECHA','MONTO','CURRENCY','STATUS','PAYER','PAYER_EMAIL','APPOINTMENT_ID','PROVIDER','REFERENCE']);
            $query->chunk(500, function($chunk) use ($out){
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->id,
                        optional($p->created_at)->format('Y-m-d H:i:s'),
                        number_format(($p->amount_cents / 100), 2, '.', ''),
                        $p->currency,
                        $p->status,
                        $p->user?->name,
                        $p->user?->email,
                        $p->meta['appointment_id'] ?? null,
                        $p->provider,
                        $p->provider_charge_id,
                    ]);
                }
            });
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function confirm(Request $request, Payment $payment)
    {
        $user = $this->resolveProfessional($request);

        if ((int) $payment->recipient_user_id !== (int) $user->id) {
            abort(403);
        }
        if ($payment->type !== 'payout') {
            return $this->errorResponse($request, 'Sólo se pueden confirmar payouts.');
        }
        if ($payment->status !== 'pending') {
            return $this->errorResponse($request, 'Este pago ya fue confirmado o cancelado.');
        }

        $now = now();
        $meta = is_array($payment->meta) ? $payment->meta : (array) ($payment->meta ?? []);
        $meta['recipient_confirmation'] = [
            'at' => $now->toIso8601String(),
            'ip' => $request->ip(),
        ];

        $payment->fill([
            'status' => 'succeeded',
            'confirmed_by_user_id' => $user->id,
            'confirmed_at' => $now,
            'meta' => $meta,
        ]);
        $payment->save();

        $message = 'Pago confirmado exitosamente.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'payment' => $payment->fresh()]);
        }
        return redirect()->back()->with('success', $message);
    }

    public function requestCardTransfer(Request $request)
    {
        $user = $this->resolveProfessional($request);

        $validated = $request->validate([
            'amount' => ['required'],
            'currency' => ['nullable','string','max:8'],
            'card_holder' => ['required','string','max:120'],
            'card_last4' => ['required','digits:4'],
            'card_brand' => ['nullable','string','max:40'],
            'notes' => ['nullable','string','max:300'],
        ]);

        $amountFloat = floatval(str_replace(',', '.', (string) $validated['amount']));
        $amountCents = (int) round($amountFloat * 100);
        if ($amountCents <= 0) {
            return $this->errorResponse($request, 'El monto debe ser mayor a cero.');
        }
        if ($amountCents > self::MAX_SIMULATED_PAYOUT_CENTS) {
            $maxFormatted = number_format(self::MAX_SIMULATED_PAYOUT_CENTS / 100, 2);
            return $this->errorResponse($request, 'El monto máximo por retiro es $' . $maxFormatted . '.');
        }

        $balanceStats = $this->payoutStats($user->id);
        $availablePendingCents = (int) ($balanceStats['pending_cents'] ?? 0);
        if ($availablePendingCents <= 0) {
            return $this->errorResponse($request, 'No tienes saldo pendiente para retirar en este momento.');
        }
        if ($amountCents > $availablePendingCents) {
            $availableFormatted = number_format($availablePendingCents / 100, 2);
            return $this->errorResponse($request, 'Solo puedes retirar hasta $' . $availableFormatted . ' de tu saldo pendiente.');
        }

        $currency = strtoupper($validated['currency'] ?? 'USD');
        $meta = [
            'initiated_by' => 'professional',
            'destination' => 'card',
            'card_last4' => $validated['card_last4'],
            'card_holder' => $validated['card_holder'],
            'card_brand' => $validated['card_brand'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'recipient_user_id' => $user->id,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider' => 'card_simulator',
            'provider_charge_id' => 'SIM-' . Str::upper(Str::random(8)),
            'status' => 'pending',
            'type' => 'payout',
            'meta' => $meta,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'payment' => $payment]);
        }
        return redirect()->back()->with('success', 'Solicitud registrada. Te avisaremos cuando debas confirmar la recepción.');
    }

    private function resolveProfessional(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('professional') && !$user->can('professionalarea'))) {
            abort(403);
        }
        return $user;
    }

    private function errorResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }
        return redirect()->back()->with('error', $message)->withInput();
    }

    private function payoutStats(int $userId): array
    {
        $payments = Payment::where('recipient_user_id', $userId)
            ->where('type', 'payout')
            ->get(['amount_cents', 'status', 'meta', 'provider']);

        $totals = [
            'pending_cents' => 0,
            'confirmed_cents' => 0,
            'failed_cents' => 0,
        ];

        foreach ($payments as $payment) {
            $amount = (int) $payment->amount_cents;
            $isDebit = $this->isDebitPayout($payment);
            $signedAmount = $isDebit ? -$amount : $amount;

            switch ($payment->status) {
                case 'pending':
                    $totals['pending_cents'] += $signedAmount;
                    break;
                case 'succeeded':
                    $totals['confirmed_cents'] += $signedAmount;
                    break;
                case 'failed':
                    $totals['failed_cents'] += abs($amount);
                    break;
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = max(0, $value);
        }

        return $totals;
    }

    private function isDebitPayout(Payment $payment): bool
    {
        $meta = $payment->meta ?? [];
        if (!is_array($meta)) {
            $meta = (array) $meta;
        }

        if (($meta['initiated_by'] ?? null) === 'professional') {
            return true;
        }

        if (($meta['destination'] ?? null) === 'card' && ($payment->provider === 'card_simulator')) {
            return true;
        }

        return false;
    }
}

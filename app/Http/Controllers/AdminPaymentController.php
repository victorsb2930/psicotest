<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;
use App\Mail\ProfessionalPayoutNotification;

class AdminPaymentController extends Controller
{
    // Require adminarea permission in routes
    public function index(Request $request)
    {
        $query = Payment::with(['user','recipient','subscription'])->orderByDesc('created_at');
        // Optional filters
        if ($request->filled('type')) $query->where('type', $request->input('type'));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        $payments = $query->paginate(50);
        $totals = $this->platformTotals();
        // Format amounts as human readable values (divide cents by 100)
        $platform_received = number_format(($totals['received'] ?: 0) / 100, 2);
        $platform_payouts = number_format(($totals['payouts'] ?: 0) / 100, 2);
        $platform_balance = number_format(($totals['balance'] ?: 0) / 100, 2);

        return view(
            'admin.payments.index',
            array_merge(
                compact('payments', 'platform_received', 'platform_payouts', 'platform_balance'),
                [
                    'platform_balance_cents' => $totals['balance'],
                    'platform_received_cents' => $totals['received'],
                    'platform_payouts_cents' => $totals['payouts'],
                ]
            )
        );
    }

    public function payout(Request $request)
    {
        $this->validate($request, [
            'recipient_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['required'],
            'currency' => ['nullable','string'],
            'appointments_count' => ['nullable','integer','min:0'],
            'period' => ['nullable','string','max:32'],
            'rate' => ['nullable','string','max:32'],
            'notes' => ['nullable','string','max:500'],
        ]);
        $user = $request->user();
        $recipient = (int) $request->input('recipient_user_id');
        $amount = $request->input('amount');
        // Accept decimal amount in user-friendly form (e.g. 12.34)
        $amountFloat = floatval(str_replace(',', '.', (string)$amount));
        $amountCents = (int) round(max(0, $amountFloat) * 100);
        $currency = $request->input('currency') ?: 'USD';
        $appointmentsCount = max(0, (int) $request->input('appointments_count', 0));
        $period = $request->input('period', 'custom');
        $rate = $request->input('rate');
        $notes = $request->input('notes');

        if ($amountCents <= 0) {
            return response()->json(['ok' => false, 'message' => 'El monto debe ser mayor a cero.'], 422);
        }

        $balanceCents = $this->platformTotals()['balance'];
        if ($amountCents > $balanceCents) {
            $available = number_format($balanceCents / 100, 2);
            return response()->json([
                'ok' => false,
                'message' => "Fondos insuficientes. Disponible: $available",
            ], 422);
        }

        $meta = [
            'appointments_count' => $appointmentsCount,
            'period' => $period,
        ];
        if ($rate !== null && $rate !== '') { $meta['rate'] = $rate; }
        if ($notes !== null && trim($notes) !== '') { $meta['notes'] = trim($notes); }

        try {
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => null,
                'recipient_user_id' => $recipient,
                'amount_cents' => $amountCents,
                'currency' => strtoupper($currency),
                'provider' => 'manual',
                'provider_charge_id' => null,
                'status' => 'pending',
                'type' => 'payout',
                'meta' => $meta,
            ]);

            try {
                $payment->loadMissing(['recipient','user']);
                if ($payment->recipient && $payment->recipient->email) {
                    Mail::to($payment->recipient->email)->send(new ProfessionalPayoutNotification($payment));
                }
            } catch (\Throwable $mailEx) {
                try { \Illuminate\Support\Facades\Log::warning('admin.payout.mail_failed', ['payment_id' => $payment->id ?? null, 'error' => $mailEx->getMessage()]); } catch (\Throwable $_) {}
            }
            return response()->json(['ok' => true, 'payment' => $payment]);
        } catch (\Throwable $e) {
            try { \Illuminate\Support\Facades\Log::error('admin.payout.failed', ['err' => $e->getMessage()]); } catch (\Throwable $_) {}
            return response()->json(['ok' => false, 'message' => 'error_creating_payout'], 500);
        }
    }

    private function platformTotals(): array
    {
        // Compute platform totals: received from sales (recipient = platform owner) and payouts made to professionals
        $platformOwnerId = env('PLATFORM_OWNER_USER_ID') ? (int) env('PLATFORM_OWNER_USER_ID') : null;
        if ($platformOwnerId) {
            $platformReceivedCents = Payment::where('type', 'sale')
                ->where('recipient_user_id', $platformOwnerId)
                ->sum('amount_cents');
        } else {
            // Fallback: sum all sales if no platform owner configured
            $platformReceivedCents = Payment::where('type', 'sale')->sum('amount_cents');
        }
        $platformPayoutsCents = Payment::where('type', 'payout')->sum('amount_cents');
        $platformBalanceCents = max(0, (int) $platformReceivedCents - (int) $platformPayoutsCents);

        return [
            'received' => (int) $platformReceivedCents,
            'payouts' => (int) $platformPayoutsCents,
            'balance' => $platformBalanceCents,
        ];
    }
}

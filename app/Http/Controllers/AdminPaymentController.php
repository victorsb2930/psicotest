<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;

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
        // Compute platform totals: received from sales (recipient = platform owner) and payouts made to professionals
        $platformOwnerId = env('PLATFORM_OWNER_USER_ID') ? (int) env('PLATFORM_OWNER_USER_ID') : null;
        if ($platformOwnerId) {
            $platformReceivedCents = Payment::where('type', 'sale')->where('recipient_user_id', $platformOwnerId)->sum('amount_cents');
        } else {
            // Fallback: sum all sales if no platform owner configured
            $platformReceivedCents = Payment::where('type', 'sale')->sum('amount_cents');
        }
        $platformPayoutsCents = Payment::where('type', 'payout')->sum('amount_cents');
        $platformBalanceCents = max(0, (int)$platformReceivedCents - (int)$platformPayoutsCents);

        // Format amounts as human readable values (divide cents by 100)
        $platform_received = number_format(($platformReceivedCents ?: 0) / 100, 2);
        $platform_payouts = number_format(($platformPayoutsCents ?: 0) / 100, 2);
        $platform_balance = number_format(($platformBalanceCents ?: 0) / 100, 2);

        return view('admin.payments.index', compact('payments', 'platform_received', 'platform_payouts', 'platform_balance'));
    }

    public function payout(Request $request)
    {
        $this->validate($request, [
            'recipient_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['required'],
            'currency' => ['nullable','string'],
        ]);
        $user = $request->user();
        $recipient = (int) $request->input('recipient_user_id');
        $amount = $request->input('amount');
        // Accept decimal amount in user-friendly form (e.g. 12.34)
        $amountFloat = floatval(str_replace(',', '.', (string)$amount));
        $amountCents = (int) round(max(0, $amountFloat) * 100);
        $currency = $request->input('currency') ?: 'USD';

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
            ]);
            return response()->json(['ok' => true, 'payment' => $payment]);
        } catch (\Throwable $e) {
            try { \Illuminate\Support\Facades\Log::error('admin.payout.failed', ['err' => $e->getMessage()]); } catch (\Throwable $_) {}
            return response()->json(['ok' => false, 'message' => 'error_creating_payout'], 500);
        }
    }
}

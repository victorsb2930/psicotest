<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\FakeInvoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\AppointmentCreditTransaction;
use Carbon\Carbon;

class BillingController extends Controller
{
    // Simulate subscription creation. In production replace with provider integration.
    public function subscribe(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok'=>false,'message'=>'Unauthenticated'], 401);
        $planKey = $request->input('plan');
        if (!$planKey) return response()->json(['ok'=>false,'message'=>'no_plan'], 400);

        $months = (int)$request->input('months', 1);
        if ($months < 1) $months = 1;
        if ($months > 24) $months = 24; // limit long extensions

        $plan = Plan::where('key', $planKey)->first();
        if (!$plan) return response()->json(['ok'=>false,'message'=>'plan_not_found'], 404);

        // Find current active subscription (if any)
        $activeSub = $user->subscriptions()->with('plan')->where(function($q){
            $q->where('status','active')
              ->orWhereNull('ends_at')
              ->orWhere('ends_at','>', now());
        })->orderBy('ends_at','desc')->first();

        $resultSub = null;

        DB::transaction(function() use ($user, $plan, $months, $activeSub, &$resultSub) {
            $now = now();

            // If user already has active subscription to the same plan => extend
            if ($activeSub && $activeSub->plan_id === $plan->id) {
                $base = $activeSub->ends_at && $activeSub->ends_at->isAfter($now) ? $activeSub->ends_at : $now;
                $activeSub->ends_at = $base->copy()->addMonths($months);
                $activeSub->save();
                $resultSub = $activeSub;

                // Compute amount and apply any multi-month discounts defined on plan.features
                $baseAmount = (int)$plan->price_cents * $months;
                $discountPercent = 0;
                try {
                    $features = is_array($plan->features ?? null) ? $plan->features : (is_object($plan->features ?? null) ? (array)$plan->features : []);
                    if (isset($features['discount_percent'])) $discountPercent = (float)$features['discount_percent'];
                    if (isset($features['multi_month_discounts']) && is_array($features['multi_month_discounts'])) {
                        $best = 0;
                        foreach ($features['multi_month_discounts'] as $d) {
                            $th = isset($d['months']) ? (int)$d['months'] : (isset($d['min']) ? (int)$d['min'] : 0);
                            $pc = isset($d['percent']) ? (float)$d['percent'] : (isset($d['p']) ? (float)$d['p'] : 0);
                            if ($months >= $th && $pc > $best) $best = $pc;
                        }
                        if ($best > $discountPercent) $discountPercent = $best;
                    }
                } catch (\Throwable $_) { $discountPercent = 0; }
                $amount = (int) round($baseAmount * (1 - max(0, min(100, $discountPercent)) / 100.0));
                $recipientId = (int) env('PLATFORM_OWNER_USER_ID', 0) ?: null;
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'subscription_id' => $activeSub->id,
                    'recipient_user_id' => $recipientId,
                    'amount_cents' => $amount,
                    'currency' => $plan->currency,
                    'provider' => 'simulated',
                    'provider_charge_id' => null,
                    'status' => 'succeeded',
                    'type' => 'sale',
                ]);

                try {
                    Mail::to($user->email)->send(new FakeInvoice($user, $activeSub, $payment, $plan));
                } catch (\Throwable $_) { }

                return;
            }

            // If user has a different active subscription, end it and create a new one (upgrade/downgrade)
            if ($activeSub && $activeSub->plan_id !== $plan->id) {
                // End previous subscription now
                try {
                    $activeSub->ends_at = $now;
                    $activeSub->status = 'cancelled';
                    $activeSub->save();
                } catch (\Throwable $_) { }
            }

            // Create new subscription
            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addMonths($months),
                'meta' => ['simulated' => true],
            ]);
            $resultSub = $sub;

            // Compute amount and apply multi-month discounts (same logic as above)
            $baseAmount = (int)$plan->price_cents * $months;
            $discountPercent = 0;
            try {
                $features = is_array($plan->features ?? null) ? $plan->features : (is_object($plan->features ?? null) ? (array)$plan->features : []);
                if (isset($features['discount_percent'])) $discountPercent = (float)$features['discount_percent'];
                if (isset($features['multi_month_discounts']) && is_array($features['multi_month_discounts'])) {
                    $best = 0;
                    foreach ($features['multi_month_discounts'] as $d) {
                        $th = isset($d['months']) ? (int)$d['months'] : (isset($d['min']) ? (int)$d['min'] : 0);
                        $pc = isset($d['percent']) ? (float)$d['percent'] : (isset($d['p']) ? (float)$d['p'] : 0);
                        if ($months >= $th && $pc > $best) $best = $pc;
                    }
                    if ($best > $discountPercent) $discountPercent = $best;
                }
            } catch (\Throwable $_) { $discountPercent = 0; }
            $amount = (int) round($baseAmount * (1 - max(0, min(100, $discountPercent)) / 100.0));
            $recipientId = (int) env('PLATFORM_OWNER_USER_ID', 0) ?: null;
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'recipient_user_id' => $recipientId,
                'amount_cents' => $amount,
                'currency' => $plan->currency,
                'provider' => 'simulated',
                'provider_charge_id' => null,
                'status' => 'succeeded',
                'type' => 'sale',
            ]);

            // Send fake invoice to user + HR emails (best-effort)
            try {
                $recipientList = [$user->email];
                try {
                    $hr = trim((string)env('HR_EMAILS', ''));
                    if ($hr !== '') {
                        $parts = array_filter(array_map('trim', explode(',', $hr)));
                        foreach ($parts as $e) if (!empty($e)) $recipientList[] = $e;
                    }
                } catch (\Throwable $_) { }
                $recipientList = array_values(array_unique($recipientList));
                if (!empty($recipientList)) {
                    Mail::to($recipientList)->send(new FakeInvoice($user, $sub, $payment, $plan));
                }
            } catch (\Throwable $_) { }
        });

        if (!$resultSub) return response()->json(['ok'=>false,'message'=>'subscription_failed'], 500);

        // Hydrate plan data for response
        $planKey = $plan->key;
        $priceCents = (int)$plan->price_cents;
        $endsAt = $resultSub->ends_at ? $resultSub->ends_at->toIso8601String() : null;

        return response()->json([
            'ok' => true,
            'subscription_id' => $resultSub->id,
            'plan' => $planKey,
            'activePlanKey' => $planKey,
            'activePriceCents' => $priceCents,
            'ends_at' => $endsAt,
        ]);
    }

    // Return remaining appointment credits for the authenticated user
    public function appointmentCredits(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);

        // Find active subscription (if any)
        $activeSub = $user->subscriptions()->with('plan')->where(function($q){
            $q->where('status','active')
              ->orWhereNull('ends_at')
              ->orWhere('ends_at','>', now());
        })->orderBy('ends_at','desc')->first();

        $included = null;
        if ($activeSub && $activeSub->plan && is_array($activeSub->plan->features ?? null)) {
            $included = $activeSub->plan->features['appointments_included_per_month'] ?? null;
            if (is_string($included)) $included = (int)$included;
        }

        // Count used appointments this calendar month
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $used = \App\Models\Appointment::where('patient_id', $user->id)->whereBetween('created_at', [$start, $end])->count();

        // Purchased credits are now persisted in appointment_credit_transactions ledger
        $purchased = (int) AppointmentCreditTransaction::getBalanceForUser($user->id);

        $remainingIncluded = null;
        if ($included !== null) {
            $remainingIncluded = max(0, (int)$included - (int)$used);
        }

        $total = ($remainingIncluded === null) ? -1 : ($remainingIncluded + $purchased);

        return response()->json(['ok' => true, 'included_remaining' => $remainingIncluded, 'purchased_credits' => $purchased, 'credits' => $total]);
    }

    // Simulate purchase of a single appointment credit (increments cached credits)
    public function purchaseAppointment(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);

        try {
            // Record a purchase transaction in the ledger
            $tx = AppointmentCreditTransaction::createPurchase($user->id, 1, ['source' => 'simulated']);
            // Optionally record a simulated payment
            try {
                $recipientId = (int) env('PLATFORM_OWNER_USER_ID', 0) ?: null;
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'subscription_id' => null,
                    'recipient_user_id' => $recipientId,
                    'amount_cents' => 0,
                    'currency' => 'USD',
                    'provider' => 'simulated',
                    'provider_charge_id' => null,
                    'status' => 'succeeded',
                    'type' => 'sale',
                ]);
            } catch (\Throwable $_) { Log::info('purchaseAppointment: failed to create payment record'); }

            $new = AppointmentCreditTransaction::getBalanceForUser($user->id);
            return response()->json(['ok' => true, 'credits' => (int)$new]);
        } catch (\Throwable $e) {
            Log::error('purchaseAppointment failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'purchase_failed'], 500);
        }
    }
}

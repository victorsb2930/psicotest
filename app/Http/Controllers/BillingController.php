<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\FakeInvoice;

class BillingController extends Controller
{
    // Simulate subscription creation. In production replace with provider integration.
    public function subscribe(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok'=>false,'message'=>'Unauthenticated'], 401);

        $planKey = $request->input('plan');
        if (!$planKey) return response()->json(['ok'=>false,'message'=>'no_plan'], 400);

        $plan = Plan::where('key', $planKey)->first();
        if (!$plan) return response()->json(['ok'=>false,'message'=>'plan_not_found'], 404);

        // Prevent duplicate active subscription for same plan/user
        $existing = $user->subscriptions()->where('plan_id',$plan->id)->where('status','active')->first();
        if ($existing) {
            return response()->json(['ok'=>false,'message'=>'already_subscribed','subscription_id'=>$existing->id], 409);
        }

        // Create subscription and a simulated payment within a transaction
        $sub = null;
        DB::transaction(function() use ($user, $plan, &$sub) {
            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'meta' => ['simulated' => true],
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'provider' => 'simulated',
                'provider_charge_id' => null,
                'status' => 'succeeded',
            ]);

            // Send a fake invoice email (best-effort) to the buyer and to platform HR emails configured in HR_EMAILS
            try {
                $recipientList = [$user->email];
                try {
                    $hr = trim((string)env('HR_EMAILS', ''));
                    if ($hr !== '') {
                        $parts = array_filter(array_map('trim', explode(',', $hr)));
                        foreach ($parts as $e) if (!empty($e)) $recipientList[] = $e;
                    }
                } catch (\Throwable $_) { /* ignore env parse errors */ }

                // Ensure unique
                $recipientList = array_values(array_unique($recipientList));
                if (!empty($recipientList)) {
                    Mail::to($recipientList)->send(new FakeInvoice($user, $sub, $payment, $plan));
                }
            } catch (\Throwable $_) {
                // Do not fail the transaction if email sending fails in this simulation
            }
        });

        return response()->json(['ok' => true, 'subscription_id' => $sub->id, 'plan' => $plan->key]);
    }
}

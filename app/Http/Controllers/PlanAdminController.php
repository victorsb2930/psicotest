<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;

class PlanAdminController extends Controller
{
    // Update plan pricing and monthly discount (admin only)
    public function updatePricing(Request $request, Plan $plan)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);

        // Route should be protected by middleware('perm:adminarea') but validate here too
        $data = $request->validate([
            'price_cents' => ['required','integer','min:0'],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'visible_role_ids' => ['nullable','array'],
            'visible_role_ids.*' => ['integer'],
            'multi_month_discounts' => ['nullable','array'],
            'multi_month_discounts.*.months' => ['required_with:multi_month_discounts','integer','min:1'],
            'multi_month_discounts.*.percent' => ['required_with:multi_month_discounts','numeric','min:0','max:100']
        ]);

        $plan->price_cents = (int)$data['price_cents'];
        $discount = isset($data['discount_percent']) ? (float)$data['discount_percent'] : null;
        $features = $plan->features ?? [];
        if ($discount === null) {
            if (array_key_exists('discount_percent', $features)) unset($features['discount_percent']);
        } else {
            $features['discount_percent'] = $discount;
        }
        // Visible roles: if provided, store as array of ints in features.visible_roles
        if (array_key_exists('visible_role_ids', $data)) {
            $features['visible_roles'] = array_values(array_map('intval', $data['visible_role_ids'] ?? []));
        }
        // Multi-month discounts: optional array like [{months:3,percent:10},...]
        if (array_key_exists('multi_month_discounts', $data)) {
            $m = $data['multi_month_discounts'] ?? [];
            // Normalize entries to ints/floats
            $features['multi_month_discounts'] = array_values(array_map(function($it){
                return ['months' => (int)($it['months'] ?? 0), 'percent' => (float)($it['percent'] ?? 0.0)];
            }, $m));
        }
        $plan->features = $features;
        $plan->save();

        return response()->json(['ok' => true, 'plan' => [ 'id' => $plan->id, 'key' => $plan->key, 'price_cents' => $plan->price_cents, 'features' => $plan->features ]]);
    }
}

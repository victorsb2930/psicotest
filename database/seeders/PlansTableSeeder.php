<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            ['key' => 'free', 'name' => 'Free', 'price_cents' => 0, 'currency' => 'USD', 'interval' => 'month', 'features' => json_encode(['chats_per_month' => 5, 'appointments_included_per_month' => 0, 'discount_percent' => 0])],
            ['key' => 'basico', 'name' => 'Básico', 'price_cents' => 499, 'currency' => 'USD', 'interval' => 'month', 'features' => json_encode(['chats_per_month' => 50, 'appointments_included_per_month' => 1, 'discount_percent' => 0])],
            ['key' => 'plus', 'name' => 'Plus', 'price_cents' => 1299, 'currency' => 'USD', 'interval' => 'month', 'features' => json_encode(['chats_per_month' => 500, 'appointments_included_per_month' => 3, 'discount_percent' => 10])],
            ['key' => 'premium', 'name' => 'Premium', 'price_cents' => 2999, 'currency' => 'USD', 'interval' => 'month', 'features' => json_encode(['chats_per_month' => null, 'appointments_included_per_month' => 6, 'discount_percent' => 15])],
        ];

        foreach ($plans as $p) {
            DB::table('plans')->updateOrInsert(['key' => $p['key']], array_merge($p, ['updated_at' => now(), 'created_at' => now()]));
        }
    }
}

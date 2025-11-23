<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\User;
use Throwable;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure base roles exist
        foreach (['admin','professional','user'] as $rn) {
            try { SpatieRole::findOrCreate($rn, 'web'); } catch (Throwable $e) { /* ignore */ }
        }

        $now = now();
        $hasIsActive = Schema::hasColumn('users','is_active');

        // Normal user
        $userEmail = env('SEED_USER_EMAIL', 'user@example.com');
        $userPass = env('SEED_USER_PASSWORD', 'password');
        $user = User::firstOrNew(['email' => strtolower($userEmail)]);
        $user->name = $user->name ?: 'Usuario';
        $user->lastname = $user->lastname ?: 'User';
        $user->birthdate = $user->birthdate ?: $now->copy()->subYears(28)->toDateString();
        $user->gender = $user->gender ?: 'Masculino';
        $user->location = $user->location ?: 'Santo Domingo';
        if (!$user->exists) {
            $user->password = Hash::make($userPass);
        }
        $user->email_verified_at = $user->email_verified_at ?: $now;
        if ($hasIsActive) { $user->is_active = true; }
        $user->save();
        try { $user->syncRoles(['user']); } catch (Throwable $_) {}

        // Professional user
        $proEmail = env('SEED_PRO_EMAIL', 'pro@example.com');
        $proPass = env('SEED_PRO_PASSWORD', 'password');
        $pro = User::firstOrNew(['email' => strtolower($proEmail)]);
        $pro->name = $pro->name ?: 'Usuario';
        $pro->lastname = $pro->lastname ?: 'Profesional';
        $pro->birthdate = $pro->birthdate ?: $now->copy()->subYears(32)->toDateString();
        $pro->gender = $pro->gender ?: 'Masculino';
        $pro->location = $pro->location ?: 'Barahona';
        $pro->speciality = $pro->speciality ?: 'Psicología';
        // Keep string like elsewhere in seeders; app casts may handle array
        $pro->appointment_types = $pro->appointment_types ?: 'virtual';
        if (!$pro->exists) {
            $pro->password = Hash::make($proPass);
        }
        $pro->email_verified_at = $pro->email_verified_at ?: $now;
        if ($hasIsActive) { $pro->is_active = true; }
        $pro->save();
        try { $pro->syncRoles(['professional']); } catch (Throwable $_) {}

        // Optional: attach free plan to normal user if exists
        try {
            if (Schema::hasTable('plans')) {
                $plan = \App\Models\Plan::where('key','free')->orWhere('price_cents',0)->first();
                if ($plan && method_exists($user,'subscriptions')) {
                    $exists = $user->subscriptions()->where('plan_id',$plan->id)->where('status','active')->exists();
                    if (!$exists) {
                        \App\Models\Subscription::create([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'status' => 'active',
                            'starts_at' => $now,
                            'ends_at' => null,
                            'meta' => ['seeded' => true],
                        ]);
                    }
                }
            }
        } catch (Throwable $_) {}

        if ($this->command) {
            $this->command->info('Seeded test accounts:');
            $this->command->info("  User:       {$userEmail} / {$userPass}");
            $this->command->info("  Professional: {$proEmail} / {$proPass}");
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserLogin;

class TestEndSessionGuard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:end-session-guard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user_login and simulate endSession guard behavior';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = 'test-heartbeat@example.com';
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::create(['name' => 'Test', 'email' => $email, 'password' => bcrypt('secret')]);
            $this->info('Created test user ' . $email);
        } else {
            $this->info('Using existing user ' . $email);
        }

        // Create a fresh user_login with started_at = now()
        $ul = UserLogin::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-123',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'cli-test',
            'started_at' => now(),
        ]);
        $this->info('Created user_login id=' . $ul->id . ' started_at=' . ($ul->started_at ? $ul->started_at->toDateTimeString() : 'null'));

        $minSeconds = (int) env('SESSION_CLOSE_MIN_SECONDS', 5);

        // Simulate evaluating whether endSession would close this row
        $startedTs = $ul->started_at instanceof \DateTimeInterface ? $ul->started_at->getTimestamp() : strtotime((string)$ul->started_at);
        $age = now()->getTimestamp() - ($startedTs ?: now()->getTimestamp());
        if ($age < $minSeconds) {
            $this->info("Simulated endSession: SKIPPED (age={$age}s < min={$minSeconds}s)");
        } else {
            $this->info("Simulated endSession: WOULD CLOSE (age={$age}s >= min={$minSeconds}s)");
        }

        // Now create another row that started 10 seconds ago and show it WOULD close
        $ul2 = UserLogin::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-older',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'cli-test',
            'started_at' => now()->subSeconds(10),
        ]);
        $this->info('Created user_login id=' . $ul2->id . ' started_at=' . ($ul2->started_at ? $ul2->started_at->toDateTimeString() : 'null'));
        $startedTs2 = $ul2->started_at instanceof \DateTimeInterface ? $ul2->started_at->getTimestamp() : strtotime((string)$ul2->started_at);
        $age2 = now()->getTimestamp() - ($startedTs2 ?: now()->getTimestamp());
        if ($age2 < $minSeconds) {
            $this->info("Simulated endSession for older: SKIPPED (age={$age2}s < min={$minSeconds}s)");
        } else {
            $this->info("Simulated endSession for older: WOULD CLOSE (age={$age2}s >= min={$minSeconds}s)");
        }

        $this->info('Test complete.');
        return 0;
    }
}

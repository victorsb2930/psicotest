<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CloseStaleSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:close-stale {--hours=24 : Close sessions older than N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close user_logins sessions that have no ended_at and started more than N hours ago';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        if ($hours <= 0) $hours = 24;
        if (!Schema::hasTable('user_logins')) {
            $this->warn('Table user_logins does not exist.');
            return 1;
        }

        $cutoff = now()->subHours($hours)->toDateTimeString();

        $rows = DB::table('user_logins')
            ->whereNull('ended_at')
            ->where('started_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            try {
                $ended = now();
                $duration = (int) max(0, $ended->getTimestamp() - (strtotime($r->started_at) ?: $ended->getTimestamp()));
                DB::table('user_logins')->where('id', $r->id)->update([
                    'ended_at' => $ended->toDateTimeString(),
                    'duration_seconds' => $duration,
                ]);
                $count++;
            } catch (\Throwable $e) {
                // ignore individual failures
            }
        }

        $this->info("Closed {$count} stale sessions older than {$hours} hours.");
        return 0;
    }
}

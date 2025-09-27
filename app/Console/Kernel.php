<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\CloseStaleSessions::class,
        \App\Console\Commands\ScanSecurityEvents::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Close sessions older than 24 hours once a day at 02:00
        $schedule->command('sessions:close-stale --hours=24')->dailyAt('02:00')->withoutOverlapping();
        // Run security log scanner periodically to detect suspicious token reuse events
        $schedule->command('security:scan-events')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        // load commands from Commands folder
        $this->load(__DIR__ . '/Commands');
    }
}

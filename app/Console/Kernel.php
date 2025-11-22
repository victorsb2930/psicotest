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
        \App\Console\Commands\FinalizeAppointmentsCommand::class,
        \App\Console\Commands\QueueAppointmentRemindersCommand::class,
        \App\Console\Commands\ApplyAppointmentPenaltiesCommand::class,
        \App\Console\Commands\DetectNoShowCommand::class,
        \App\Console\Commands\AggregateDailyAppointmentMetricsCommand::class,
        \App\Console\Commands\AppointmentMetricsAlertsCommand::class,
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
        // Finalize appointments (completion / no-show / skipped) every 5 minutes
        $schedule->command('appointments:finalize')->everyFiveMinutes()->withoutOverlapping();
        // Queue appointment reminders every 15 minutes
        $schedule->command('appointments:queue-reminders')->everyFifteenMinutes()->withoutOverlapping();
        // Apply penalties for skipped/no-show appointments every 30 minutes
        $schedule->command('appointments:apply-penalties')->everyThirtyMinutes()->withoutOverlapping();
        // Early classification of no-show/skipped during window
        $schedule->command('appointments:detect-no-show')->everyFiveMinutes()->withoutOverlapping();
        // Daily aggregation of metrics at 01:30 (after most sessions should be finalized)
        $schedule->command('appointments:aggregate-daily')->dailyAt('01:30')->withoutOverlapping();
        // Metrics alerts shortly after aggregation (01:35)
        $schedule->command('appointments:metrics-alerts')->dailyAt('01:35')->withoutOverlapping();
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

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PlanIntegrationService;

class ApplyAppointmentPenaltiesCommand extends Command
{
    protected $signature = 'appointments:apply-penalties';
    protected $description = 'Apply plan penalties for skipped / no-show appointments';

    public function handle(PlanIntegrationService $service): int
    {
        $count = $service->applyPendingPenalties();
        $this->info("Applied penalties to {$count} appointments");
        return Command::SUCCESS;
    }
}

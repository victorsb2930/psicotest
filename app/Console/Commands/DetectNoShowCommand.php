<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Services\AppointmentSessionService;

class DetectNoShowCommand extends Command
{
    protected $signature = 'appointments:detect-no-show';
    protected $description = 'Classify early no-show / skipped appointments based on join presence after grace window.';

    public function handle(): int
    {
        $grace = (int) config('appointments.no_show_grace_minutes', 10);
        $now = now();
        // Target appointments that have started (start < now - grace) but not ended yet and still in accepted status
        $appts = Appointment::whereIn('status',['accepted','in_progress'])
            ->where('start','<',$now->copy()->subMinutes($grace))
            ->where('end','>',$now) // still ongoing window
            ->get();
        $countClassified = 0;
        $service = app(AppointmentSessionService::class);
        foreach ($appts as $appt) {
            $result = $service->earlyClassify($appt, $grace);
            if($result){
                $countClassified++;
                $this->info("Appointment {$appt->id} early marked {$result}");
            }
        }
        $this->info("Processed {$appts->count()} active, classified {$countClassified}.");
        return Command::SUCCESS;
    }
}

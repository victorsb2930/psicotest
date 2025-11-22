<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class AppointmentMetricsAlertsCommand extends Command
{
    protected $signature = 'appointments:metrics-alerts {--day= : YYYY-MM-DD day (default yesterday)}';
    protected $description = 'Evaluate daily aggregated metrics against thresholds and log or notify warnings.';

    public function handle(): int
    {
        $dayInput = $this->option('day');
        $day = $dayInput ? Carbon::parse($dayInput)->startOfDay() : now()->subDay()->startOfDay();
        $dayStr = $day->toDateString();
        $row = DB::table('appointment_metrics_daily')->where('day',$dayStr)->first();
        if(!$row){ $this->warn('No aggregated row for '.$dayStr); return self::SUCCESS; }
        $alertsCfg = config('appointments.alerts', []);
        $violations = [];
        // Compute derived percentages
        $noShowPct = ($row->total_appointments ?? 0) > 0 ? ($row->no_show_count / $row->total_appointments) * 100 : 0;
        $map = [
            'loss_pct_warn' => $row->avg_loss_pct,
            'rtt_ms_warn' => $row->avg_rtt_ms,
            'retries_warn' => $row->avg_retries,
            'no_show_pct_warn' => $noShowPct,
        ];
        foreach($map as $key => $value){
            $thr = (float) ($alertsCfg[$key] ?? INF);
            if(is_numeric($value) && $value >= $thr){
                $violations[] = [$key, $value, $thr];
            }
        }
        if(empty($violations)){
            $this->info('No threshold violations for '.$dayStr);
            return self::SUCCESS;
        }
        foreach($violations as [$k,$v,$t]){
            $msg = sprintf('metrics.alert %s=%.2f threshold=%.2f day=%s', $k, $v, $t, $dayStr);
            Log::warning($msg);
            $this->warn($msg);
        }
        // TODO: integrate notification system (email/Admin model) if required
        return self::SUCCESS;
    }
}

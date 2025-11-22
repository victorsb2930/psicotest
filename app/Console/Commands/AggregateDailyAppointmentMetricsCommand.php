<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AggregateDailyAppointmentMetricsCommand extends Command
{
    protected $signature = 'appointments:aggregate-daily {--day= : YYYY-MM-DD day to aggregate (default yesterday)}';
    protected $description = 'Aggregate appointment session metrics and status counts into daily table.';

    public function handle(): int
    {
        $dayInput = $this->option('day');
        $day = $dayInput ? Carbon::parse($dayInput)->startOfDay() : now()->subDay()->startOfDay();
        $dayStr = $day->toDateString();
        $startTs = $day->copy();
        $endTs = $day->copy()->endOfDay();
        $this->info('Aggregating for day '.$dayStr);
        // Guard: skip if already present
        $exists = DB::table('appointment_metrics_daily')->where('day',$dayStr)->exists();
        if($exists){ $this->warn('Row already exists for day '.$dayStr); return self::SUCCESS; }
        // Status counts from appointments table
        $apptQuery = DB::table('appointments')->whereBetween('start', [$startTs, $endTs]);
        $totalAppts = (int) $apptQuery->count();
        $completed = (int) DB::table('appointments')->whereBetween('start', [$startTs, $endTs])->where('status','completed')->count();
        $noShow = (int) DB::table('appointments')->whereBetween('start', [$startTs, $endTs])->where('status','no_show')->count();
        $skipped = (int) DB::table('appointments')->whereBetween('start', [$startTs, $endTs])->where('status','skipped')->count();
        // Metrics summaries
        $logs = DB::table('appointment_session_logs')
            ->select(['payload'])
            ->where('event_type','metrics_summary')
            ->whereBetween('created_at', [$startTs, $endTs])
            ->get();
        $metricsSessions = $logs->count();
        $sumBit = 0; $sumLoss = 0; $sumRtt = 0; $sumRetries = 0; $degradedTotal = 0;
        foreach($logs as $row){
            $payload = json_decode($row->payload ?? '[]', true);
            if(!is_array($payload)) continue;
            $sumBit += (float)($payload['avg_bitrate_kbps'] ?? 0);
            $sumLoss += (float)($payload['avg_loss_pct'] ?? 0);
            $sumRtt += (float)($payload['avg_rtt_ms'] ?? 0);
            $sumRetries += (int)($payload['total_retries'] ?? 0);
            $degradedTotal += (int)($payload['degraded_sequences'] ?? 0);
        }
        $avgBit = $metricsSessions > 0 ? $sumBit / $metricsSessions : null;
        $avgLoss = $metricsSessions > 0 ? $sumLoss / $metricsSessions : null;
        $avgRtt = $metricsSessions > 0 ? $sumRtt / $metricsSessions : null;
        $avgRetries = $metricsSessions > 0 ? $sumRetries / $metricsSessions : null;
        DB::table('appointment_metrics_daily')->insert([
            'day' => $dayStr,
            'total_appointments' => $totalAppts,
            'completed_count' => $completed,
            'no_show_count' => $noShow,
            'skipped_count' => $skipped,
            'metrics_sessions' => $metricsSessions,
            'avg_bitrate_kbps' => $avgBit,
            'avg_loss_pct' => $avgLoss,
            'avg_rtt_ms' => $avgRtt,
            'avg_retries' => $avgRetries,
            'degraded_sequences_total' => $degradedTotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Prune retention
        $retentionDays = (int) config('appointments.aggregation_retention_days', 180);
        $cutoff = now()->subDays($retentionDays)->startOfDay()->toDateString();
        try { DB::table('appointment_metrics_daily')->where('day','<',$cutoff)->delete(); } catch(\Throwable $e) { }
        $this->info('Aggregation complete. Sessions with metrics: '.$metricsSessions);
        return self::SUCCESS;
    }
}

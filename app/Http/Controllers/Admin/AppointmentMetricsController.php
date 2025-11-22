<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AppointmentMetricsController extends Controller
{
    public function index()
    {
        // Basic permission gate: requires adminarea perm
        $user = auth()->user();
        if(!$user || !$user->can('adminarea')){ abort(403); }
        $rows = DB::table('appointment_metrics_daily')->orderByDesc('day')->limit(60)->get();
        // Summary aggregates
        $summary = [
            'days' => $rows->count(),
            'avg_bitrate_kbps' => $rows->avg('avg_bitrate_kbps'),
            'avg_loss_pct' => $rows->avg('avg_loss_pct'),
            'avg_rtt_ms' => $rows->avg('avg_rtt_ms'),
            'avg_retries' => $rows->avg('avg_retries'),
            'total_appointments' => $rows->sum('total_appointments'),
            'completed_pct' => $rows->sum('total_appointments') > 0 ? ($rows->sum('completed_count') / $rows->sum('total_appointments')) * 100 : null,
            'no_show_pct' => $rows->sum('total_appointments') > 0 ? ($rows->sum('no_show_count') / $rows->sum('total_appointments')) * 100 : null,
            'skipped_pct' => $rows->sum('total_appointments') > 0 ? ($rows->sum('skipped_count') / $rows->sum('total_appointments')) * 100 : null,
        ];
        return view('admin.appointment_metrics.index', compact('rows','summary'));
    }

    public function json()
    {
        $user = auth()->user();
        if(!$user || !$user->can('adminarea')){ return response()->json(['ok'=>false], 403); }
        $limit = (int) request('limit', 60); if($limit < 1) $limit = 1; if($limit > 120) $limit = 120;
        $rows = \Illuminate\Support\Facades\DB::table('appointment_metrics_daily')->orderByDesc('day')->limit($limit)->get();
        $data = $rows->map(function($r){
            return [
                'day' => $r->day,
                'total' => (int)$r->total_appointments,
                'completed' => (int)$r->completed_count,
                'no_show' => (int)$r->no_show_count,
                'skipped' => (int)$r->skipped_count,
                'metrics_sessions' => (int)$r->metrics_sessions,
                'avg_bitrate_kbps' => $r->avg_bitrate_kbps !== null ? (float)$r->avg_bitrate_kbps : null,
                'avg_loss_pct' => $r->avg_loss_pct !== null ? (float)$r->avg_loss_pct : null,
                'avg_rtt_ms' => $r->avg_rtt_ms !== null ? (float)$r->avg_rtt_ms : null,
                'avg_retries' => $r->avg_retries !== null ? (float)$r->avg_retries : null,
                'degraded_sequences_total' => (int)$r->degraded_sequences_total,
            ];
        });
        return response()->json(['ok'=>true,'days'=>$data]);
    }
}

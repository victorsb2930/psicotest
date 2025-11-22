<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSession;
use App\Models\AppointmentSessionLog;
use App\Models\AppointmentAudit;
use App\Events\AppointmentStarted;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentSkipped;
use App\Services\PlanIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AppointmentSessionService
{
    public function generateRoomId(Appointment $appointment): string
    {
        // Deterministic room id to avoid duplicates (prefix + id + date)
        return 'appt-' . $appointment->id . '-' . $appointment->start?->format('Ymd');
    }

    public function ensureSession(Appointment $appointment): AppointmentSession
    {
        return DB::transaction(function () use ($appointment) {
            $session = AppointmentSession::firstOrCreate(
                ['appointment_id' => $appointment->id],
                ['room_id' => $appointment->room_id ?: $this->generateRoomId($appointment)]
            );
            if (!$appointment->room_id) {
                $appointment->room_id = $session->room_id;
                $appointment->save();
            }
            return $session;
        });
    }

    public function start(Appointment $appointment): AppointmentSession
    {
        $session = $this->ensureSession($appointment);
        $statusBefore = $appointment->status;
        $justStarted = false;
        if (!$session->started_at) {
            $session->started_at = now();
            $session->save();
            $justStarted = true;
        }
        // Move appointment to in_progress if accepted/pending and within window
        if (in_array($appointment->status, ['accepted','pending']) && $this->isWithinEarlyAccessWindow($appointment)) {
            $appointment->status = 'in_progress';
            $appointment->save();
        }
        if ($justStarted) {
            AppointmentAudit::record($appointment, 'started', $statusBefore, $appointment->status, ['session_id' => $session->id]);
            event(new AppointmentStarted($appointment));
        }
        return $session;
    }

    public function heartbeat(Appointment $appointment, int $userId, bool $isProfessional): void
    {
        $session = $this->ensureSession($appointment);
        $now = now();
        // Join timestamps
        if ($isProfessional && !$session->professional_joined_at) {
            $session->professional_joined_at = $now;
        }
        if (!$isProfessional && !$session->patient_joined_at) {
            $session->patient_joined_at = $now;
        }
        // Presence accumulation (simplified: add ping interval each call if joined)
        $interval = config('appointments.ping_interval_seconds');
        if ($isProfessional) {
            $session->professional_presence_seconds += $interval;
        } else {
            $session->patient_presence_seconds += $interval;
        }
        $session->save();
    }

    public function complete(Appointment $appointment): void
    {
        $session = $this->ensureSession($appointment);
        if (!$session->ended_at) {
            $session->ended_at = now();
            $session->save();
        }
        $thresholdPct = config('appointments.presence_threshold_pct');
        $durationSeconds = max(1, $appointment->end?->diffInSeconds($appointment->start));
        $profPct = ($session->professional_presence_seconds / $durationSeconds) * 100;
        $patientPct = ($session->patient_presence_seconds / $durationSeconds) * 100;
        $fromStatus = $appointment->status;
        if ($profPct >= $thresholdPct && $patientPct >= $thresholdPct) {
            $appointment->status = 'completed';
        } elseif ($profPct >= $thresholdPct && $patientPct < $thresholdPct) {
            $appointment->status = 'no_show';
        } else {
            $appointment->status = 'skipped';
        }
        $appointment->save();
        $action = match($appointment->status) {
            'completed' => 'completed',
            'no_show' => 'no_show',
            'skipped' => 'skipped',
            default => 'completed'
        };
        AppointmentAudit::record($appointment, $action, $fromStatus, $appointment->status, [
            'prof_pct' => round($profPct,2),
            'patient_pct' => round($patientPct,2),
            'duration_seconds' => $durationSeconds,
        ]);
        if ($appointment->status === 'completed') {
            event(new AppointmentCompleted($appointment));
        } else {
            event(new AppointmentSkipped($appointment));
        }
        // Plan integration stub
        try { app(PlanIntegrationService::class)->handlePostCompletion($appointment); } catch (\Throwable $e) { /* ignore */ }
    }

    public function isWithinEarlyAccessWindow(Appointment $appointment): bool
    {
        $minutes = config('appointments.early_access_minutes');
        return now()->between($appointment->start->copy()->subMinutes($minutes), $appointment->end);
    }

    /**
     * Early classification for no_show / skipped before appointment end.
     * Returns new status string if changed, or null if no action.
     */
    public function earlyClassify(Appointment $appointment, int $graceMinutes): ?string
    {
        $now = now();
        if (!in_array($appointment->status, ['accepted','in_progress'], true)) return null;
        if ($appointment->status === 'reschedule_pending') return null;
        // Within active window: start passed + grace, not yet ended
        if (!($appointment->start->lt($now->copy()->subMinutes($graceMinutes)) && $appointment->end->gt($now))) return null;
        $session = AppointmentSession::where('appointment_id', $appointment->id)->first();
        if (!$session) return null;
        $profJoined = (bool) $session->professional_joined_at;
        $patientJoined = (bool) $session->patient_joined_at;
        if ($profJoined && $patientJoined) return null; // both present
        $from = $appointment->status;
        if (!$profJoined && !$patientJoined) {
            $appointment->status = 'no_show';
            $appointment->save();
            AppointmentAudit::record($appointment,'early_no_show',$from,$appointment->status,[ 'grace_minutes'=>$graceMinutes ]);
            event(new AppointmentSkipped($appointment)); // treat as skipped event family
            return $appointment->status;
        }
        if ($profJoined xor $patientJoined) {
            $appointment->status = 'skipped';
            $appointment->save();
            AppointmentAudit::record($appointment,'early_skipped',$from,$appointment->status,[ 'grace_minutes'=>$graceMinutes, 'prof_joined'=>$profJoined, 'patient_joined'=>$patientJoined ]);
            event(new AppointmentSkipped($appointment));
            return $appointment->status;
        }
        return null;
    }

    /**
     * Persist metrics summary from client (reconnects, quality averages, counts) and audit.
     */
    public function logMetrics(Appointment $appointment, array $data): void
    {
        $session = $this->ensureSession($appointment);
        try {
            AppointmentSessionLog::create([
                'appointment_id' => $appointment->id,
                'appointment_session_id' => $session->id,
                'event_type' => 'metrics_summary',
                'payload' => $data,
            ]);
            AppointmentAudit::record($appointment,'session_metrics',$appointment->status,$appointment->status,[
                'metrics_id' => null,
                'retries' => $data['total_retries'] ?? null,
                'degraded_sequences' => $data['degraded_sequences'] ?? null,
                'avg_bitrate_kbps' => $data['avg_bitrate_kbps'] ?? null,
                'avg_loss_pct' => $data['avg_loss_pct'] ?? null,
                'avg_rtt_ms' => $data['avg_rtt_ms'] ?? null,
            ]);
        } catch (\Throwable $e) { /* swallow */ }
    }
}

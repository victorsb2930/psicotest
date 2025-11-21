<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSession;
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
}

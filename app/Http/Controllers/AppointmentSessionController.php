<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppointmentSessionController extends Controller
{
    public function __construct(private AppointmentSessionService $service) {}

    public function start(Request $request, Appointment $appointment)
    {
        $this->authorize('startSession', $appointment);
        $session = $this->service->start($appointment);
        return response()->json(['ok'=>true,'session'=>[ 'id'=>$session->id, 'room_id'=>$session->room_id, 'started_at'=>optional($session->started_at)->toIso8601String() ]]);
    }

    public function heartbeat(Request $request, Appointment $appointment)
    {
        $this->authorize('heartbeat', $appointment);
        $userId = (int) auth()->id();
        $isProfessional = $appointment->professional_id === $userId;
        $this->service->heartbeat($appointment, $userId, $isProfessional);
        return response()->json(['ok'=>true]);
    }

    public function complete(Request $request, Appointment $appointment)
    {
        $this->authorize('completeSession', $appointment);
        $this->service->complete($appointment);
        return response()->json(['ok'=>true,'status'=>$appointment->status]);
    }

    public function status(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        $session = $this->service->ensureSession($appointment);
        return response()->json([
            'ok' => true,
            'appointment_id' => $appointment->id,
            'status' => $appointment->status,
            'session' => [
                'room_id' => $session->room_id,
                'started_at' => optional($session->started_at)->toIso8601String(),
                'ended_at' => optional($session->ended_at)->toIso8601String(),
                'professional_joined_at' => optional($session->professional_joined_at)->toIso8601String(),
                'patient_joined_at' => optional($session->patient_joined_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Ensure an appointment session has a room id and return it.
     * Clients can call this when attempting to join a conference and the room_id is missing.
     */
    public function ensureRoom(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        $session = $this->service->ensureSession($appointment);
        return response()->json(['ok' => true, 'room_id' => $session->room_id]);
    }

    public function metrics(Request $request, Appointment $appointment)
    {
        // Only participants can submit metrics (view policy covers both roles)
        $this->authorize('view', $appointment);
        $raw = $request->all();
        $allowed = [
            'total_retries' => 'int',
            'degraded_sequences' => 'int',
            'avg_bitrate_kbps' => 'float',
            'avg_loss_pct' => 'float',
            'avg_rtt_ms' => 'float',
            'duration_seconds' => 'int',
            'presence_heartbeats_sent' => 'int',
            'samples' => 'array'
        ];
        $out = [];
        // Reject unknown keys early to prevent payload bloat
        foreach ($allowed as $key => $type) {
            if (!array_key_exists($key, $raw)) continue;
            $val = $raw[$key];
            switch ($type) {
                case 'int':
                    if (!is_numeric($val)) continue 2; // skip invalid
                    $val = (int) $val;
                    break;
                case 'float':
                    if (!is_numeric($val)) continue 2;
                    $val = (float) $val;
                    break;
                case 'array':
                    if (!is_array($val)) { $val = null; }
                    break;
            }
            $out[$key] = $val;
        }
        // Bounds enforcement to avoid extreme / poisoning values
        $bounds = [
            'total_retries' => [0, 500],
            'degraded_sequences' => [0, 10000],
            'avg_bitrate_kbps' => [0, 200000],
            'avg_loss_pct' => [0, 100],
            'avg_rtt_ms' => [0, 10000],
            'duration_seconds' => [0, 86400],
            'presence_heartbeats_sent' => [0, 200000],
        ];
        foreach ($bounds as $k => [$min,$max]) {
            if (isset($out[$k])) {
                if ($out[$k] < $min) $out[$k] = $min;
                if ($out[$k] > $max) $out[$k] = $max;
            }
        }
        // Trim samples length according to config (if provided)
        $maxSamples = (int) (config('appointments.quality.max_samples') ?? 200);
        if (isset($out['samples']) && is_array($out['samples'])) {
            if (count($out['samples']) > $maxSamples) {
                $out['samples'] = array_slice($out['samples'], 0, $maxSamples);
            }
            // Per-sample sanitation (only keep numeric basic fields)
            foreach ($out['samples'] as $i => $sample) {
                if (!is_array($sample)) { unset($out['samples'][$i]); continue; }
                $cleanSample = [];
                foreach (["bitrate_kbps","loss_pct","rtt_ms"] as $sf) {
                    if (isset($sample[$sf]) && is_numeric($sample[$sf])) {
                        $cleanSample[$sf] = (float) $sample[$sf];
                    }
                }
                $out['samples'][$i] = $cleanSample;
            }
            $out['samples'] = array_values($out['samples']); // reindex
        }
        // Enforce maximum payload size (~8KB) after sanitation
        $jsonSize = strlen(json_encode($out));
        if ($jsonSize > 8192) {
            // Drop samples first if too large
            if (isset($out['samples'])) {
                unset($out['samples']);
                $jsonSize = strlen(json_encode($out));
            }
        }
        if ($jsonSize > 8192) {
            return response()->json(['ok'=>false,'error'=>'metrics_payload_too_large'], 413);
        }
        $this->service->logMetrics($appointment, $out);
        return response()->json(['ok'=>true]);
    }

    /**
     * Request the other participant to confirm ending the session.
     * This broadcasts an application-level event so frontends listening
     * via Echo can show a confirm/decline modal.
     */
    public function requestEnd(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        $userId = (int) auth()->id();
        // Store a short-lived pending end-request in cache so recipients can poll if broadcasting fails
        try {
            $key = "appointment:{$appointment->id}:end_request";
            Cache::put($key, ['requester_id' => $userId, 'ts' => now()->toDateTimeString()], 70); // 70s TTL
        } catch (\Throwable $_) { }
        try {
            event(new \App\Events\AppointmentEndRequested($appointment, $userId));
        } catch (\Throwable $e) {
            try { \Log::error('appointment.request_end.broadcast_failed', ['appointment_id'=>$appointment->id,'user_id'=>$userId,'err'=>$e->getMessage()]); } catch (\Throwable $_) {}
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Return any pending end-request for this appointment (used by clients polling as a fallback).
     */
    public function endRequestStatus(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        try {
            $key = "appointment:{$appointment->id}:end_request";
            $data = Cache::get($key, null);
            return response()->json(['ok' => true, 'pending' => $data]);
        } catch (\Throwable $_) {
            return response()->json(['ok' => true, 'pending' => null]);
        }
    }

    /**
     * Cancel a pending end-request (called by requester cancel action).
     */
    public function cancelEnd(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        $userId = (int) auth()->id();
        try {
            $key = "appointment:{$appointment->id}:end_request";
            Cache::forget($key);
        } catch (\Throwable $_) { }
        try { event(new \App\Events\AppointmentEndCancelled($appointment, $userId)); } catch (\Throwable $_) { }
        return response()->json(['ok' => true]);
    }

    // Legacy method removed; policies now handle authorization.
}

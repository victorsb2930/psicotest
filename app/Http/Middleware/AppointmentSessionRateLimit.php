<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppointmentSessionRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $appointmentParam = $request->route('appointment');
        $appointmentId = null;
        if (is_object($appointmentParam) && method_exists($appointmentParam, 'getKey')) {
            $appointmentId = $appointmentParam->getKey();
        } elseif (is_numeric($appointmentParam)) {
            $appointmentId = (int) $appointmentParam;
        }
        if (!$appointmentId) {
            return response()->json(['ok' => false, 'error' => 'missing_appointment'], 400);
        }

        $routeName = $request->route()->getName();
        $isMetrics = $routeName === 'appointments.session.metrics';
        $isHeartbeat = $routeName === 'appointments.session.heartbeat';
        if (!$isMetrics && !$isHeartbeat) {
            return $next($request);
        }

        if ($isMetrics && !$this->checkMetricsLimit($user->id, $appointmentId)) {
            return $this->limitedResponse('metrics');
        }

        if ($isHeartbeat && !$this->checkHeartbeatLimit($user->id, $appointmentId)) {
            return $this->limitedResponse('heartbeat');
        }

        return $next($request);
    }

    private function limitedResponse(string $scope)
    {
        $retry = $scope === 'metrics'
            ? (int) config('rate_limits.metrics.retry_after_seconds', 15)
            : (int) config('rate_limits.heartbeat.retry_after_seconds', 5);

        $payload = [
            'ok' => false,
            'error' => 'rate_limited',
            'scope' => $scope,
            'retry_after' => $retry,
        ];
        return response()->json($payload, 429, [
            'Retry-After' => (string) $retry,
        ]);
    }

    private function checkMetricsLimit(int $userId, int $appointmentId): bool
    {
        $limit = (int) config('rate_limits.metrics.limit', 30);
        $period = (int) config('rate_limits.metrics.period_seconds', 60);
        $key = 'rate:metrics:' . $userId . ':' . $appointmentId;

        $created = Cache::add($key, 0, $period);
        $count = Cache::increment($key);

        if ($count > $limit) {
            $this->logViolation('metrics', $userId, $appointmentId, $count, $limit);
            return false;
        }
        return true;
    }

    private function checkHeartbeatLimit(int $userId, int $appointmentId): bool
    {
        $minInterval = (float) config('rate_limits.heartbeat.min_interval_seconds', 8);
        $burstLeeway = (int) config('rate_limits.heartbeat.burst_leeway', 2);
        $windowLimit = (int) config('rate_limits.heartbeat.window.limit', 120);
        $windowPeriod = (int) config('rate_limits.heartbeat.window.period_seconds', 900);

        $lastTsKey = 'rate:heartbeat:last_ts:' . $userId . ':' . $appointmentId;
        $countKey = 'rate:heartbeat:count:' . $userId . ':' . $appointmentId;

        $now = microtime(true);
        $last = Cache::get($lastTsKey);

        $created = Cache::add($countKey, 0, $windowPeriod);
        $count = Cache::increment($countKey);

        if ($count > $windowLimit) {
            $this->logViolation('heartbeat_window', $userId, $appointmentId, $count, $windowLimit);
            return false;
        }

        if ($count <= $burstLeeway) {
            Cache::put($lastTsKey, $now, $windowPeriod);
            return true;
        }

        if (is_numeric($last)) {
            $delta = $now - (float) $last;
            if ($delta < $minInterval) {
                $this->logViolation('heartbeat_interval', $userId, $appointmentId, $delta, $minInterval);
                return false;
            }
        }

        Cache::put($lastTsKey, $now, $windowPeriod);
        return true;
    }

    private function logViolation(string $type, int $userId, int $appointmentId, $value, $limit)
    {
        $sampleEvery = (int) config('rate_limits.logging.violation_sample_every', 5);
        $counterKey = 'rate:violations:' . $type . ':' . $userId . ':' . $appointmentId;
        Cache::add($counterKey, 0, 3600);
        $c = Cache::increment($counterKey);
        if ($c % $sampleEvery === 0) {
            try {
                Log::warning('rate_limit.violation', [
                    'scope' => $type,
                    'user_id' => $userId,
                    'appointment_id' => $appointmentId,
                    'value' => $value,
                    'limit' => $limit,
                    'sample_count' => $c,
                ]);
            } catch (\Throwable $e) {
            }
        }
    }
}

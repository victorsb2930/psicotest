<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ProfessionalAvailabilityApiController extends Controller
{
    public function check(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'string'],
            'end' => ['required', 'string'],
        ]);

        try {
            $start = Carbon::parse($request->input('start'));
            $end = Carbon::parse($request->input('end'));
        } catch (\Throwable $e) {
            return response()->json(['available' => false, 'error' => 'invalid_datetime'], 422);
        }

        if ($end->lessThanOrEqualTo($start)) {
            return response()->json(['available' => false, 'error' => 'invalid_range'], 422);
        }

        [$ok, $reason] = app(AvailabilityService::class)->isSlotAvailable($id, $start, $end);
        return response()->json(['available' => $ok, 'reason' => $reason]);
    }

    public function weekly(Request $request, int $id): JsonResponse
    {
        // Return raw weekly availability + raw exceptions (upcoming 30 days) for client rendering
        $weekly = \App\Models\ProfessionalAvailability::where('user_id', $id)
            ->where('active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get(['day_of_week','start_time','end_time']);

        $exceptions = \App\Models\ProfessionalAvailabilityException::where('user_id', $id)
            ->whereDate('date', '>=', now()->toDateString())
            ->whereDate('date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('date')
            ->get(['date','status','start_time','end_time']);

        return response()->json([
            'weekly' => $weekly->map(fn($w) => [
                'day_of_week' => (int)$w->day_of_week,
                'start_time' => $w->start_time,
                'end_time' => $w->end_time,
            ])->values(),
            'exceptions' => $exceptions->map(fn($e) => [
                'date' => $e->date->toDateString(),
                'status' => $e->status,
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
            ])->values(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentSessionService;
use Illuminate\Http\Request;

class AppointmentSessionController extends Controller
{
    public function __construct(private AppointmentSessionService $service) {}

    public function start(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($appointment);
        $session = $this->service->start($appointment);
        return response()->json(['ok'=>true,'session'=>[ 'id'=>$session->id, 'room_id'=>$session->room_id, 'started_at'=>optional($session->started_at)->toIso8601String() ]]);
    }

    public function heartbeat(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($appointment);
        $userId = (int) auth()->id();
        $isProfessional = $appointment->professional_id === $userId;
        $this->service->heartbeat($appointment, $userId, $isProfessional);
        return response()->json(['ok'=>true]);
    }

    public function complete(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($appointment);
        $this->service->complete($appointment);
        return response()->json(['ok'=>true,'status'=>$appointment->status]);
    }

    protected function authorizeAccess(Appointment $appointment): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->id, [$appointment->professional_id, $appointment->patient_id], true)) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function accept(Request $request, $appointment)
    {
        $user = Auth::user();
        // load appointment explicitly (avoid potential implicit binding oddities in tests)
        $appt = Appointment::withTrashed()->find($appointment);
        if (!$appt) {
            \Log::warning('Appointment accept: appointment not found', ['appointment_id' => $appointment, 'auth_user_id' => $user?->id]);
            return response()->json(['error' => 'No encontrado'], 404);
        }
        // extra debug information
        try {
            \Log::info('Appointment accept attempt', [
                'appointment_id' => $appt->id,
                'appointment_attributes' => $appt->toArray(),
                'db_row' => \Illuminate\Support\Facades\DB::table('appointments')->where('id', $appt->id)->first(),
                'auth_user_id' => $user?->id,
            ]);
        } catch (\Throwable $ex) {
            \Log::error('Error logging appointment debug data', ['err' => $ex->getMessage()]);
        }

        // only patient can accept their invitation (compare as ints)
        if ((int)($appt->patient_id ?? 0) !== (int)$user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $appt->status = 'accepted';
        $appt->save();
        try {
            $appt->professional->notify(new \App\Notifications\AppointmentAccepted($appt));
        } catch (\Throwable $ex) {}
        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, $appointment)
    {
        $user = Auth::user();
        $appt = Appointment::withTrashed()->find($appointment);
        if (!$appt) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        if ((int)($appt->patient_id ?? 0) !== (int)$user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $appt->status = 'rejected';
        $appt->save();
        try {
            $appt->professional->notify(new \App\Notifications\AppointmentRejected($appt));
        } catch (\Throwable $ex) {}
        return response()->json(['ok' => true]);
    }
}

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

		// Authorization: only the professional can accept
		$isProfessional = ((int)($appt->professional_id ?? 0) === (int)$user->id);
		if (!$isProfessional) {
			return response()->json(['error' => 'No autorizado'], 403);
		}

		$appt->status = 'accepted';
		$appt->save();

		// Notify the patient that the professional accepted
		try {
			if ($appt->patient) {
				$appt->patient->notify(new \App\Notifications\AppointmentAccepted($appt));
			}
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
		// Authorization: only the professional may reject
		$isProfessional = ((int)($appt->professional_id ?? 0) === (int)$user->id);
		if (!$isProfessional) {
			return response()->json(['error' => 'No autorizado'], 403);
		}
		// Reason is now mandatory
		$data = $request->validate([
			'reason' => ['required','string','min:3']
		]);
		$reason = $data['reason'];
		$appt->status = 'rejected';
		$appt->rejection_reason = $reason;
		$appt->save();
		try {
			// notify the patient and include the professional-provided reason when available
			if ($appt->patient) { $appt->patient->notify(new \App\Notifications\AppointmentRejected($appt, $reason)); }
		} catch (\Throwable $ex) {}

		return response()->json(['ok' => true]);
	}

	public function cancel(Request $request, $appointment)
	{
		$user = Auth::user();
		$appt = Appointment::withTrashed()->find($appointment);
		if (!$appt) {
			return response()->json(['error' => 'No encontrado'], 404);
		}
		// Authorization: only the patient who requested can cancel
		$isPatient = ((int)($appt->patient_id ?? 0) === (int)$user->id);
		if (!$isPatient) {
			return response()->json(['error' => 'No autorizado'], 403);
		}
		if ($appt->status !== 'pending') {
			return response()->json(['error' => 'Solo se puede cancelar si está pendiente'], 422);
		}
		$appt->status = 'cancelled';
		$appt->save();
		$reason = $request->input('reason') ?? null;
		// Notify the professional that the patient cancelled
		try {
			if ($appt->professional) {
				$appt->professional->notify(new \App\Notifications\AppointmentCancelled($appt, $reason));
			}
		} catch (\Throwable $ex) {}
		return response()->json(['ok' => true]);
	}
}

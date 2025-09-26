<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use Carbon\Carbon;

class UserAppointmentController extends Controller
{
    // show a calendar view for the authenticated user
    public function index()
    {
        return view('appointments.index');
    }

    // return events where the authenticated user is the patient
    public function events(Request $request)
    {
        $user = Auth::user();
        $events = Appointment::where('patient_id', $user->id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function($a){
                return [
                    'id' => $a->id,
                    'title' => $a->title ?: ($a->professional?->name ?? 'Cita'),
                    'start' => $a->start?->setTimezone('UTC')->toIso8601String(),
                    'end' => $a->end?->setTimezone('UTC')->toIso8601String(),
                    'allDay' => (bool) $a->all_day,
                    'status' => $a->status,
                ];
            });
        return response()->json($events);
    }

    // Allow a patient to request a new appointment (simple flow)
    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'professional_id' => ['required','integer'],
            'title' => ['nullable','string','max:255'],
            'start' => ['required','date'],
            'end' => ['nullable','date','after:start'],
            'notes' => ['nullable','string']
        ]);
        $start = Carbon::parse($data['start'])->setTimezone('UTC');
        $end = isset($data['end']) ? Carbon::parse($data['end'])->setTimezone('UTC') : null;

        $appt = Appointment::create([
            'professional_id' => $data['professional_id'],
            'patient_id' => $user->id,
            'title' => $data['title'] ?? null,
            'start' => $start->toDateTimeString(),
            'end' => $end?->toDateTimeString() ?? null,
            'all_day' => false,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending'
        ]);

        try { $appt->professional->notify(new \App\Mail\ContactMessage($appt)); } catch (\Throwable $e) {}

        return response()->json(['ok' => true, 'appointment' => $appt]);
    }
}

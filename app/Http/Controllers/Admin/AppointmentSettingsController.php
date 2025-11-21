<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppointmentSetting;
use Illuminate\Http\Request;

class AppointmentSettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = AppointmentSetting::first();
        if (!$settings) {
            $settings = AppointmentSetting::create([
                'presence_threshold_pct' => config('appointments.presence_threshold_pct'),
                'early_access_minutes' => config('appointments.early_access_minutes'),
                'reschedule_deadline_hours' => config('appointments.reschedule_deadline_hours'),
                'unanswered_reprogram_hours' => config('appointments.unanswered_reprogram_hours'),
                'ping_interval_seconds' => config('appointments.ping_interval_seconds'),
            ]);
        }
        return view('admin.appointment_settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $settings = AppointmentSetting::first();
        if (!$settings) {
            $settings = AppointmentSetting::create([
                'presence_threshold_pct' => config('appointments.presence_threshold_pct'),
                'early_access_minutes' => config('appointments.early_access_minutes'),
                'reschedule_deadline_hours' => config('appointments.reschedule_deadline_hours'),
                'unanswered_reprogram_hours' => config('appointments.unanswered_reprogram_hours'),
                'ping_interval_seconds' => config('appointments.ping_interval_seconds'),
            ]);
        }
        $data = $request->validate([
            'presence_threshold_pct' => ['required','integer','min:50','max:100'],
            'early_access_minutes' => ['required','integer','min:0','max:120'],
            'reschedule_deadline_hours' => ['required','integer','min:1','max:168'],
            'unanswered_reprogram_hours' => ['required','integer','min:1','max:72'],
            'ping_interval_seconds' => ['required','integer','min:15','max:300'],
        ]);
        $settings->fill($data)->save();

        if ($request->wantsJson()) {
            return response()->json(['ok'=>true,'settings'=>$settings]);
        }
        return redirect()->route('admin.appointment.settings')->with('success','Configuraciones actualizadas');
    }
}

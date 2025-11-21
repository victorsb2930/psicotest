<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appointment;

class TestFutureAppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $prof = User::whereHas('roles', function($q){ $q->where('name','professional'); })->first();
        $pat = User::whereHas('roles', function($q){ $q->where('name','user'); })->first();
        if(!$prof || !$pat) {
            echo "[TestFutureAppointmentSeeder] Missing professional or user role\n"; return;
        }
        $exists = Appointment::where('professional_id',$prof->id)
            ->where('patient_id',$pat->id)
            ->where('start','>=', now()->subMinutes(1))
            ->where('start','<=', now()->addMinutes(10))
            ->first();
        if($exists){ echo "[TestFutureAppointmentSeeder] Existing upcoming appointment ID={$exists->id}\n"; return; }
        $a = Appointment::create([
            'professional_id' => $prof->id,
            'patient_id' => $pat->id,
            'title' => 'Test VC Auto',
            'start' => now()->addMinutes(5),
            'end' => now()->addMinutes(25),
            'status' => 'accepted',
            'notes' => 'Generada por TestFutureAppointmentSeeder'
        ]);
        echo "[TestFutureAppointmentSeeder] Created appointment ID={$a->id}\n";
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSession extends Model
{
    protected $fillable = [
        'appointment_id','room_id','started_at','ended_at',
        'professional_joined_at','patient_joined_at','professional_left_at','patient_left_at',
        'professional_presence_seconds','patient_presence_seconds'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'professional_joined_at' => 'datetime',
        'patient_joined_at' => 'datetime',
        'professional_left_at' => 'datetime',
        'patient_left_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}

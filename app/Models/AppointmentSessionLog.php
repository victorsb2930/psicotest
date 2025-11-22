<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSessionLog extends Model
{
    protected $fillable = [
        'appointment_id','appointment_session_id','event_type','payload'
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function session()
    {
        return $this->belongsTo(AppointmentSession::class,'appointment_session_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentReschedule extends Model
{
    protected $fillable = [
        'appointment_id','requested_by','original_start','original_end','proposed_start','proposed_end','status','reason','responded_at'
    ];

    protected $casts = [
        'original_start' => 'datetime',
        'original_end' => 'datetime',
        'proposed_start' => 'datetime',
        'proposed_end' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

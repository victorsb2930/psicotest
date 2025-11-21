<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppointmentRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id','professional_id','patient_id','rating','comment','is_public','edited_at','response_text','responded_at'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'edited_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function appointment(){ return $this->belongsTo(Appointment::class); }
    public function professional(){ return $this->belongsTo(User::class,'professional_id'); }
    public function patient(){ return $this->belongsTo(User::class,'patient_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSetting extends Model
{
    protected $fillable = [
        'presence_threshold_pct',
        'early_access_minutes',
        'reschedule_deadline_hours',
        'unanswered_reprogram_hours',
        'ping_interval_seconds',
    ];
}

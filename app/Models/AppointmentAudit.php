<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentAudit extends Model
{
    protected $fillable = [
        'appointment_id','user_id','action','from_status','to_status','meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function record(Appointment $appointment, string $action, ?string $from, ?string $to, array $meta = [], ?int $userId = null): void
    {
        try {
            self::create([
                'appointment_id' => $appointment->id,
                'user_id' => $userId ?? auth()->id(),
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'meta' => empty($meta) ? null : $meta,
            ]);
        } catch (\Throwable $e) { /* swallow to avoid breaking flow */ }
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

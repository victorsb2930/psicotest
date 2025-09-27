<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLogin extends Model
{
    use HasFactory;

    protected $table = 'user_logins';

    protected $fillable = [
        'user_id', 'session_id', 'ip_address', 'user_agent', 'started_at', 'ended_at', 'duration_seconds', 'browser_token_hash'
    ];

    protected $dates = ['started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

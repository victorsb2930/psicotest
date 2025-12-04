<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'recipient_user_id',
        'confirmed_by_user_id',
        'amount_cents',
        'currency',
        'provider',
        'provider_charge_id',
        'status',
        'type',
        'meta',
        'confirmed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function recipient()
    {
        return $this->belongsTo(\App\Models\User::class, 'recipient_user_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmed_by_user_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}

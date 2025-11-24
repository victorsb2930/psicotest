<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['user_id','subscription_id','recipient_user_id','amount_cents','currency','provider','provider_charge_id','status','type','meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function recipient()
    {
        return $this->belongsTo(\App\Models\User::class, 'recipient_user_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}

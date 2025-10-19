<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['user_id','plan_id','status','starts_at','ends_at','provider','provider_id','meta'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function usages()
    {
        return $this->hasMany(SubscriptionUsage::class);
    }
}

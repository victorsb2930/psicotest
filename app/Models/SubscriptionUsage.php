<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionUsage extends Model
{
    protected $fillable = ['subscription_id','user_id','feature_key','period_start','period_end','used','limit'];

    protected $dates = ['period_start','period_end'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}

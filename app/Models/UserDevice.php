<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $table = 'user_devices';

    protected $fillable = ['user_id','token_hash','name','ip_address','user_agent','last_seen_at','revoked_at'];

    protected $dates = ['last_seen_at','revoked_at','created_at','updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

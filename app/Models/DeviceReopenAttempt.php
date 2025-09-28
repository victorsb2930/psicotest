<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceReopenAttempt extends Model
{
    use HasFactory;

    protected $table = 'device_reopen_attempts';

    protected $fillable = ['user_id','token_hash','ip_address','user_agent','success','action'];
}

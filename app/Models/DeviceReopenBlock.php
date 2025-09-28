<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceReopenBlock extends Model
{
    use HasFactory;

    protected $table = 'device_reopen_blocks';

    protected $fillable = ['user_id','token_hash','blocked_until','permanent','admin_unlocked_by','admin_unlocked_at'];
}

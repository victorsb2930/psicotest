<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatCallLog extends Model
{
    use HasFactory;

    protected $table = 'chat_call_logs';

    protected $fillable = [
        'user_id',
        'type',
    ];
}

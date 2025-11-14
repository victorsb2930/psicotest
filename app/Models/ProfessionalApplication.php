<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','titulo_path','cedula_path','cv_path','exequatur_path','status','notes','reviewed_by','reviewed_at'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}

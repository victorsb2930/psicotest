<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class FriendRequest extends Model {
    use HasFactory;
    protected $fillable=['from_id','to_id','status','accepted_at','rejected_at'];
    // Cast integer IDs and datetimes to ensure consistent types across the app
    protected $casts=[
        'from_id' => 'integer',
        'to_id' => 'integer',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
    public function from(){ return $this->belongsTo(User::class,'from_id'); }
    public function to(){ return $this->belongsTo(User::class,'to_id'); }
}
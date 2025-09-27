<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPhoto extends Model {
	use HasFactory;

	protected $table = 'user_photos';

	protected $fillable = [
		'user_id', 'foto', 'path', 'caption', 'is_profile'
	];

	public function user() {
		return $this->belongsTo(User::class);
	}
}

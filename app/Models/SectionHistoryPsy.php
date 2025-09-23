<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionHistoryPsy extends Model {
	protected $table = 'section_history_psy';
	protected $fillable = [
		'professional_id',
		'client_id',
		'session_datetime',
		'status',
		'session_type',
		'notes',
		'duration_minutes'
	];

	public function professional() {
		return $this->belongsTo(User::class, 'professional_id');
	}

	public function client() {
		return $this->belongsTo(User::class, 'client_id');
	}
}
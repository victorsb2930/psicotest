<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Appointment extends Model
{
	use SoftDeletes;

	public const STATUSES = [
		'pending','requested','accepted','in_progress','completed','skipped','no_show','cancelled','canceled','rejected','reschedule_pending'
	];

	public static function isValidStatus(string $status): bool
	{
		return in_array($status, self::STATUSES, true);
	}

	protected $fillable = ['professional_id','patient_id','title','start','end','all_day','status','notes','rejection_reason','room_id'];

	/**
	 * Cast date fields to Carbon instances and booleans
	 * so we can safely convert timezones when returning events.
	 */
	protected $casts = [
		'start' => 'datetime',
		'end' => 'datetime',
		'all_day' => 'boolean',
	];

	public function professional()
	{
		return $this->belongsTo(User::class, 'professional_id');
	}

	public function rating()
	{
		return $this->hasOne(AppointmentRating::class);
	}

	public function session()
	{
		return $this->hasOne(\App\Models\AppointmentSession::class, 'appointment_id');
	}

	public function patient()
	{
		return $this->belongsTo(User::class, 'patient_id');
	}

	/**
	 * Citas cuyo estado bloquea nueva programación.
	 * Rechazado / cancelado NO bloquea la disponibilidad.
	 * Se aplica comparación case-insensitive por seguridad.
	 */
	public function scopeBlocking($q)
	{
		$blocking = ['pending','requested','accepted','in_progress'];
		// Normaliza status en la comparación (lower + trim) para evitar falsos bloqueos por mayúsculas o espacios
		return $q->whereIn(DB::raw("LOWER(TRIM(status))"), $blocking);
	}

	public function setStatusAttribute($value)
	{
		$val = is_string($value) ? trim($value) : $value;
		if ($val && !self::isValidStatus($val)) {
			throw new \InvalidArgumentException('Estado de cita inválido: ' . $val);
		}
		$this->attributes['status'] = $val;
	}
}

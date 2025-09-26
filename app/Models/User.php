<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
	/** @use HasFactory<\Database\Factories\UserFactory> */
	use HasFactory, Notifiable, SoftDeletes, HasRoles;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'name',
		'email',
		'timezone',
		'password',
		// profile fields
		'photo',
		'specialty',
		'appointment_types',
		'location',
		'rating',
		'status',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var list<string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
			// cast appointment_types json to array
			'appointment_types' => 'array',
		];
	}

	/**
	 * Return a data URL for the photo blob if present to be used in <img src="...">.
	 */
	/**
	 * Return a data URL for the user's profile photo stored in user_photos.foto (if any).
	 */
	public function getProfilePhotoDataUrlAttribute()
	{
		$pf = $this->photos()->where('is_profile', true)->first();
		if (!$pf || empty($pf->foto)) return null;
		try {
			$base = base64_encode($pf->foto);
			return 'data:image/jpeg;base64,' . $base;
		} catch (\Throwable$e) {
			return null;
		}
	}

	// UserLevel relation removed (legacy)

	#region Relación con SectionHistoryPsy

	/*
	 * Relación uno a muchos (un profesional puede tener muchas sesiones)
	 * Relación uno a muchos (un cliente puede tener muchas sesiones)
	**/
	public function professionalSessions()
	{
		return $this->hasMany(SectionHistoryPsy::class, 'professional_id');
	}

	/**
	 * Relación uno a muchos (un cliente puede tener muchas sesiones)
	 **/
	public function clientSessions()
	{
		return $this->hasMany(SectionHistoryPsy::class, 'client_id');
	}

	/**
	 * User photos (profile and gallery)
	 */
	public function photos()
	{
		return $this->hasMany(UserPhoto::class, 'user_id');
	}
	#endregion

	/**
	 * Force emails to lowercase on assignment to avoid case-sensitivity issues.
	 * This complements the DB migration that normalizes existing values.
	 */
	public function setEmailAttribute($value)
	{
		$this->attributes['email'] = $value ? mb_strtolower($value) : $value;
	}

	// Spatie provides hasRole(), hasAnyRole(), can(), hasPermissionTo(), etc.
}

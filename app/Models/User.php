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
		];
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
	#endregion

	// Spatie provides hasRole(), hasAnyRole(), can(), hasPermissionTo(), etc.
}

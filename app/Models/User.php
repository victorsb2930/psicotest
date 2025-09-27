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
	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'email_verified_at' => 'datetime',
		'password' => 'hashed',
		// cast appointment_types json to array
		'appointment_types' => 'array',
	];

	/**
	 * Return a data URL for the photo blob if present to be used in <img src="...">.
	 */
	/**
	 * Return a data URL for the user's profile photo stored in user_photos.foto (if any).
	 */
	public function getProfilePhotoDataUrlAttribute()
	{
		$pf = $this->photos()->where('is_profile', true)->first();
		if (!$pf) return null;
		try {
			// Prefer filesystem path when present
			if (!empty($pf->path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($pf->path)) {
				$bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($pf->path);
				if ($bytes === null || $bytes === false || $bytes === '') return null;
				$base = base64_encode($bytes);
				$mime = null;
				try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
				if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
				if (!$mime) $mime = 'application/octet-stream';
				return 'data:'.$mime.';base64,' . $base;
			}
			// Fallback to blob stored in 'foto'
			if (isset($pf->foto) && $pf->foto !== null) {
				$raw = $pf->foto;
				if (is_resource($raw)) {
					try { if (ftell($raw) !== false) rewind($raw); } catch (\Throwable$_){}
					$bytes = @stream_get_contents($raw);
				} else {
					$bytes = $raw;
				}
				if ($bytes === null || $bytes === false || $bytes === '') return null;
				$base = base64_encode($bytes);
				$mime = null;
				try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
				if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
				if (!$mime) $mime = 'application/octet-stream';
				return 'data:'.$mime.';base64,' . $base;
			}
			return null;
		} catch (\Throwable$e) {
			return null;
		}
	}

	// UserLevel relation removed (legacy)

	/**
	 * User photos (profile and gallery)
	 */
	public function photos()
	{
		return $this->hasMany(UserPhoto::class, 'user_id');
	}

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

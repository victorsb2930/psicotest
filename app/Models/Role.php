<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
	use HasFactory;

	protected $fillable = [
		'name',
		'guard_name',
		'show_in_signup',
		'signup_label',
		'requires_docs',
		'icon_class',
		'badge_color',
	];
}

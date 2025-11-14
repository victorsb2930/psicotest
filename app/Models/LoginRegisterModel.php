<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginRegisterModel extends Model {
	protected $table = 'users';
	protected $fillable = ['name', 'lastname', 'birthdate', 'gender', 'email', 'speciality', 'location', 'password', 'remember_token'];
	public $timestamps = true;
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginRegisterModel extends Model {
	protected $table = 'users';
	protected $fillable = ['name', 'email', 'password', 'remember_token'];
	public $timestamps = true;
}
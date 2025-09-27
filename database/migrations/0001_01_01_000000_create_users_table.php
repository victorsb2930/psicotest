<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	**/
	public function up(): void {
		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('email')->unique();
			// timezone
			$table->string('timezone')->nullable();
			// Professional/profile metadata (images are stored in user_photos as file paths in 'path')
			$table->string('specialty')->nullable();
			$table->json('appointment_types')->nullable();
			$table->string('location')->nullable();
			$table->decimal('rating', 3, 1)->nullable();
			$table->timestamp('email_verified_at')->nullable();
			$table->string('password');
			$table->boolean('is_active')->default(true);
			// deactivation audit
			$table->text('deactivated_reason')->nullable();
			$table->timestamp('deactivated_at')->nullable();
			// presence/status fields
			$table->string('status', 32)->default('online')->index();
			$table->timestamp('last_seen_at')->nullable()->index();
			$table->rememberToken();
			$table->softDeletes();
			$table->timestamps();
		});

		Schema::create('password_reset_tokens', function (Blueprint $table) {
			$table->string('email')->primary();
			$table->string('token');
			$table->timestamp('created_at')->nullable();
		});

		Schema::create('sessions', function (Blueprint $table) {
			$table->string('id')->primary();
			$table->foreignId('user_id')->nullable()->index();
			$table->string('ip_address', 45)->nullable();
			$table->text('user_agent')->nullable();
			$table->longText('payload');
			$table->integer('last_activity')->index();
		});

		// user_photos table for profile and gallery images (store file path in `path`)
			Schema::create('user_photos', function (Blueprint $table) {
				$table->id();
				$table->unsignedBigInteger('user_id')->index();
				// store the storage path (e.g. user_photos/{user_id}/file.jpg)
				$table->string('path')->nullable();
				$table->string('caption')->nullable();
				$table->boolean('is_profile')->default(false)->index();
				$table->timestamps();

				$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			});
	}

	/**
	 * Reverse the migrations.
	**/
	public function down(): void {
		Schema::dropIfExists('users');
		Schema::dropIfExists('password_reset_tokens');
		Schema::dropIfExists('sessions');
	}
};

// Ensure existing emails are normalized when this migration runs in an existing DB.
// Note: this is a best-effort; if duplicates exist this may fail when creating the unique index.
// We perform a DB-specific attempt to create a case-insensitive unique constraint/index.
try {
	if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
		$driver = \Illuminate\Support\Facades\DB::getDriverName();
		\Illuminate\Support\Facades\DB::beginTransaction();
		\Illuminate\Support\Facades\DB::statement('UPDATE users SET email = LOWER(email) WHERE email IS NOT NULL');
		if ($driver === 'pgsql') {
			try { \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_idx ON users (LOWER(email));'); } catch (\Throwable$e) { /* ignore */ }
		} elseif ($driver === 'mysql') {
			try {
				\Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
				\Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX users_email_lower_idx ON users (email)');
			} catch (\Throwable$e) { /* ignore */ }
		}
		\Illuminate\Support\Facades\DB::commit();
	}
} catch (\Throwable$e) {
	try { \Illuminate\Support\Facades\DB::rollBack(); } catch(\Throwable$e){}
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user_logins', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->unsignedBigInteger('user_id')->index();
			$table->string('session_id')->nullable()->index();
			$table->string('ip_address')->nullable();
			$table->text('user_agent')->nullable();
			$table->timestamp('started_at')->nullable();
			$table->timestamp('ended_at')->nullable();
			$table->integer('duration_seconds')->nullable()->unsigned();
			$table->timestamps();

			// optional foreign key if users table exists in same connection
			if (Schema::hasTable('users')) {
				// avoid strict FK to reduce migration issues in diverse environments
				// $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			}
		});

		// Partial unique index for open sessions (PostgreSQL only), originally in add migration
		try {
			$driver = \Illuminate\Support\Facades\DB::getDriverName();
			if ($driver === 'pgsql') {
				\Illuminate\Support\Facades\DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS user_logins_unique_open_session ON user_logins (user_id, session_id) WHERE ended_at IS NULL;");
			}
		} catch (\Throwable $e) {
			// ignore
		}
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('user_logins');
	}
};

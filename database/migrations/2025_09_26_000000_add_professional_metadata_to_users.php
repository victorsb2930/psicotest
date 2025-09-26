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
		// Add profile fields to users
		if (Schema::hasTable('users')) {
			Schema::table('users', function (Blueprint $table) {
				if (! Schema::hasColumn('users', 'photo')) {
					$table->string('photo')->nullable()->after('email');
				}
				if (! Schema::hasColumn('users', 'specialty')) {
					$table->string('specialty')->nullable()->after('photo');
				}
				if (! Schema::hasColumn('users', 'appointment_types')) {
					$table->json('appointment_types')->nullable()->after('specialty');
				}
				if (! Schema::hasColumn('users', 'location')) {
					$table->string('location')->nullable()->after('appointment_types');
				}
				if (! Schema::hasColumn('users', 'rating')) {
					$table->decimal('rating', 3, 1)->nullable()->after('location');
				}
			});
		}

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		if (Schema::hasTable('users')) {
			Schema::table('users', function (Blueprint $table) {
				if (Schema::hasColumn('users', 'rating')) {
					$table->dropColumn('rating');
				}
				if (Schema::hasColumn('users', 'location')) {
					$table->dropColumn('location');
				}
				if (Schema::hasColumn('users', 'appointment_types')) {
					$table->dropColumn('appointment_types');
				}
				if (Schema::hasColumn('users', 'specialty')) {
					$table->dropColumn('specialty');
				}
				if (Schema::hasColumn('users', 'photo')) {
					$table->dropColumn('photo');
				}
			});
		}
	}
};
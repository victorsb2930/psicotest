<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	private array $statuses = [
		'pending','requested','accepted','in_progress','completed','skipped','no_show','cancelled','canceled','rejected','reschedule_pending'
	];

	public function up(): void
	{
		Schema::create('appointments', function (Blueprint $table) {
			$table->id();
			$table->foreignId('professional_id')->constrained('users')->onDelete('cascade');
			$table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
			$table->string('title')->nullable();
			$table->timestamp('start')->nullable();
			$table->timestamp('end')->nullable();
			$table->boolean('all_day')->default(false);
			$table->string('status',32)->default('pending');
			$table->string('room_id',64)->nullable()->after('status');
			$table->text('notes')->nullable();
			$table->text('rejection_reason')->nullable();
			$table->timestamp('penalty_applied_at')->nullable();
			$table->string('penalty_type',40)->nullable();
			$table->timestamps();
			$table->softDeletes();
		});
		// Añadir constraint de estados en PostgreSQL para control estricto
		$connection = config('database.default');
		$driver = config("database.connections.$connection.driver");
		if ($driver === 'pgsql') {
			$allowed = "'" . implode("','", $this->statuses) . "'";
			DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ($allowed))");
		}
	}

	public function down(): void
	{
		Schema::dropIfExists('appointments');
	}
};

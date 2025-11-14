<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('professional_applications', function (Blueprint $table) {
			$table->id()->index();
			$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
			$table->string('titulo_path')->nullable();
			$table->string('cedula_path')->nullable();
			$table->string('cv_path')->nullable();
			$table->string('exequatur_path')->nullable();
			$table->enum('status', ['pending','approved','rejected'])->default('pending');
			$table->text('notes')->nullable();
			$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
			$table->timestamp('reviewed_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void {
		Schema::dropIfExists('professional_applications');
	}
};

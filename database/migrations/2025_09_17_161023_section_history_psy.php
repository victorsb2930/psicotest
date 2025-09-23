<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('section_history_psy', function (Blueprint $table) {
			$table->id();
			// Relación al profesional (rol: professional)
			$table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
			// Relación al cliente (rol: user)
			$table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
			// Fecha/hora programada de la sesión
			$table->dateTime('session_datetime');
			// Estado de la sesión: Programado, Completado, Cancelado, No Presentado, En Progreso
			$table->string('status', 20)->default('Programado');
			// Tipo de sesión: Presencial, Video Llamada
			$table->string('session_type', 15); // 'Presencial' | 'Video Llamada'
			// Notas del profesional
			$table->text('notes')->nullable();
			// Opcional: duración real en minutos
			$table->unsignedSmallInteger('duration_minutes')->nullable();
			$table->timestamps();

			// Índice único (nombre corto para evitar límite de 64 chars en MySQL)
			$table->unique(['professional_id', 'client_id', 'session_datetime'], 'psy_session_unique');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('section_history_psy');
	}
};

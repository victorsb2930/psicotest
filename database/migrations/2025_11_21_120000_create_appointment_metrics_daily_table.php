<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('total_appointments')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('metrics_sessions')->default(0); // rows with metrics summary
            $table->decimal('avg_bitrate_kbps',10,2)->nullable();
            $table->decimal('avg_loss_pct',7,4)->nullable();
            $table->decimal('avg_rtt_ms',10,2)->nullable();
            $table->decimal('avg_retries',7,2)->nullable();
            $table->unsignedInteger('degraded_sequences_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_metrics_daily');
    }
};

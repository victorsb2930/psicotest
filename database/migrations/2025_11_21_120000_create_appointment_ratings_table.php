<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->text('response_text')->nullable(); // consolidated from add migration
            $table->boolean('is_public')->default(true);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('responded_at')->nullable(); // consolidated from add migration
            $table->timestamps();
            $table->unique('appointment_id');
            $table->index(['professional_id','rating']);
        });

        // Denormalized stats on users (if not present)
        if (!Schema::hasColumn('users', 'ratings_count')) {
            Schema::table('users', function(Blueprint $table){
                $table->unsignedInteger('ratings_count')->default(0);
                $table->decimal('ratings_avg',3,2)->default(0.00);
                $table->json('ratings_breakdown')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_ratings');
        if (Schema::hasColumn('users','ratings_count')) {
            Schema::table('users', function(Blueprint $table){
                $table->dropColumn(['ratings_count','ratings_avg','ratings_breakdown']);
            });
        }
    }
};

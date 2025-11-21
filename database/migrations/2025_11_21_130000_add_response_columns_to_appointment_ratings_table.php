<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('appointment_ratings')) {
            Schema::table('appointment_ratings', function(Blueprint $table){
                if (!Schema::hasColumn('appointment_ratings','response_text')) {
                    $table->text('response_text')->nullable()->after('comment');
                }
                if (!Schema::hasColumn('appointment_ratings','responded_at')) {
                    $table->timestamp('responded_at')->nullable()->after('edited_at');
                }
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('appointment_ratings')) {
            Schema::table('appointment_ratings', function(Blueprint $table){
                if (Schema::hasColumn('appointment_ratings','response_text')) {
                    $table->dropColumn('response_text');
                }
                if (Schema::hasColumn('appointment_ratings','responded_at')) {
                    $table->dropColumn('responded_at');
                }
            });
        }
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('professional_applications')) {
            Schema::table('professional_applications', function(Blueprint $table){
                foreach(['titulo','cedula','cv','exequatur'] as $f){
                    $col = $f.'_viewed_at';
                    if (!Schema::hasColumn('professional_applications', $col)) {
                        $table->timestamp($col)->nullable()->after('exequatur_path');
                    }
                }
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('professional_applications')) {
            Schema::table('professional_applications', function(Blueprint $table){
                foreach(['titulo','cedula','cv','exequatur'] as $f){
                    $col = $f.'_viewed_at';
                    if (Schema::hasColumn('professional_applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
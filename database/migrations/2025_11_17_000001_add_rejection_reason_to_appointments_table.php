<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (Schema::hasTable('appointments') && !Schema::hasColumn('appointments','rejection_reason')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('notes');
            });
        }
    }
    public function down() {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments','rejection_reason')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};

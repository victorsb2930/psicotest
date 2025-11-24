<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'recipient_user_id')) {
                $table->foreignId('recipient_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payments', 'type')) {
                $table->string('type')->default('sale')->after('provider');
            }
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'recipient_user_id')) {
                $table->dropConstrainedForeignId('recipient_user_id');
            }
            if (Schema::hasColumn('payments', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

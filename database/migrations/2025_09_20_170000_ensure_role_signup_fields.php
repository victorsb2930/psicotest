<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		if (! Schema::hasTable('roles')) return;
		Schema::table('roles', function (Blueprint $table) {
			if (! Schema::hasColumn('roles', 'show_in_signup')) {
				$table->boolean('show_in_signup')->default(false)->nullable();
			}
			if (! Schema::hasColumn('roles', 'signup_label')) {
				$table->string('signup_label')->nullable();
			}
			if (! Schema::hasColumn('roles', 'requires_docs')) {
				$table->boolean('requires_docs')->default(false)->nullable();
			}
		});
	}
	public function down(): void {
		if (! Schema::hasTable('roles')) return;
		Schema::table('roles', function (Blueprint $table) {
			$drop = [];
			foreach (['show_in_signup','signup_label','requires_docs'] as $col) {
				if (Schema::hasColumn('roles', $col)) $drop[] = $col;
			}
			if (!empty($drop)) {
				$table->dropColumn($drop);
			}
		});
	}
};

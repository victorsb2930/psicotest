<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		// Add guard_name to roles/permissions if missing
		if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'guard_name')) {
			Schema::table('roles', function (Blueprint $table) {
				$table->string('guard_name')->default('web')->after('name');
			});
		}
		if (Schema::hasTable('permissions') && ! Schema::hasColumn('permissions', 'guard_name')) {
			Schema::table('permissions', function (Blueprint $table) {
				$table->string('guard_name')->default('web')->after('name');
			});
		}
		// Ensure pivot tables for Spatie exist
		if (Schema::hasTable('permissions') && Schema::hasTable('roles') && ! Schema::hasTable('role_has_permissions')) {
			Schema::create('role_has_permissions', function (Blueprint $table) {
				$table->unsignedBigInteger('permission_id');
				$table->unsignedBigInteger('role_id');
				$table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
				$table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
				$table->primary(['permission_id', 'role_id']);
			});
		}
		if (Schema::hasTable('permissions') && ! Schema::hasTable('model_has_permissions')) {
			Schema::create('model_has_permissions', function (Blueprint $table) {
				$table->unsignedBigInteger('permission_id');
				$table->string('model_type');
				$table->unsignedBigInteger('model_id');
				$table->index(['model_id', 'model_type']);
				$table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
				$table->primary(['permission_id', 'model_id', 'model_type']);
			});
		}
		if (Schema::hasTable('roles') && ! Schema::hasTable('model_has_roles')) {
			Schema::create('model_has_roles', function (Blueprint $table) {
				$table->unsignedBigInteger('role_id');
				$table->string('model_type');
				$table->unsignedBigInteger('model_id');
				$table->index(['model_id', 'model_type']);
				$table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
				$table->primary(['role_id', 'model_id', 'model_type']);
			});
		}
		// Backfill guard_name values
		if (Schema::hasColumn('roles', 'guard_name')) {
			DB::table('roles')->whereNull('guard_name')->orWhere('guard_name', '')->update(['guard_name' => 'web']);
		}
		if (Schema::hasColumn('permissions', 'guard_name')) {
			DB::table('permissions')->whereNull('guard_name')->orWhere('guard_name', '')->update(['guard_name' => 'web']);
		}
	}

	public function down(): void
	{
		// Do not drop columns/tables to avoid breaking Spatie
	}
};
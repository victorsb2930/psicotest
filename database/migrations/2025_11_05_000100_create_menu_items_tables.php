<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('label');
                $table->string('route_name')->nullable();
                $table->string('url')->nullable();
                $table->string('icon_class')->nullable();
                $table->string('section')->default('user'); // admin|professional|user|common
                $table->integer('sort_order')->default(0);
                $table->boolean('enabled')->default(true);
                $table->string('permission')->nullable(); // optional permission slug required to view
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('menu_item_role')) {
            Schema::create('menu_item_role', function (Blueprint $table) {
                $table->unsignedBigInteger('menu_item_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['menu_item_id','role_id']);
                $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_item_role')) {
            Schema::drop('menu_item_role');
        }
        if (Schema::hasTable('menu_items')) {
            Schema::drop('menu_items');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Creates: plans, subscriptions, subscription_usages, payments
     *
     * @return void
     */
    public function up()
    {
        // Plans catalog
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // machine key: basico, profesional, premium
            $table->string('name');
            $table->integer('price_cents')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('interval', 16)->default('month');
            $table->json('features')->nullable(); // flexible features JSON
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Subscriptions per user
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status')->default('active'); // active, canceled, trial
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('provider')->nullable(); // stripe, payu, etc
            $table->string('provider_id')->nullable(); // provider subscription id
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });

        // Usage counters per subscription and period (e.g. per month)
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key'); // e.g. 'chats', 'appointments'
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('used')->default(0);
            $table->integer('limit')->nullable(); // null => unlimited
            $table->timestamps();

            $table->unique(['subscription_id','feature_key','period_start'], 'usage_unique_period');
            $table->index(['user_id','feature_key']);
        });

        // Payments / invoices (simple ledger for provider interactions)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->integer('amount_cents')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('provider')->nullable();
            $table->string('provider_charge_id')->nullable();
            $table->string('status')->default('pending'); // pending, succeeded, failed
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id','subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     * Drops tables in reverse order to respect FKs.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscription_usages');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};

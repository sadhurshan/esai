<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->decimal('price_usd', 12, 2)->nullable();
            $table->unsignedInteger('rfqs_per_month');
            $table->unsignedInteger('users_max');
            $table->unsignedInteger('storage_gb');
            $table->unsignedInteger('erp_integrations_max')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 160)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('stripe_id', 191)->unique();
            $table->string('pm_type', 50)->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->string('default_payment_method', 191)->nullable();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('stripe_id', 191)->unique();
            $table->string('stripe_status', 50)->nullable();
            $table->string('stripe_plan', 191)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stripe_status']);
            $table->index('customer_id');
        });

        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('stripe_id', 191)->unique();
            $table->string('stripe_product', 191)->nullable();
            $table->string('stripe_price', 191)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('plans');
    }
};

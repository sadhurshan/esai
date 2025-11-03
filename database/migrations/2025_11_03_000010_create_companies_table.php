<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('slug', 160)->unique();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->string('region', 64)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rfqs_monthly_used')->default(0);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->string('stripe_id', 191)->nullable();
            $table->enum('plan_code', ['starter', 'growth', 'enterprise'])->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('plan_code');
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

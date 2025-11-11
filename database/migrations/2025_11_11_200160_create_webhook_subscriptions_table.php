<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url', 191);
            $table->string('secret', 64);
            $table->json('events')->default(json_encode([]));
            $table->boolean('active')->default(true);
            $table->json('retry_policy_json')->default(json_encode([
                'max' => 5,
                'backoff' => 'exponential',
                'base_sec' => 30,
            ]));
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
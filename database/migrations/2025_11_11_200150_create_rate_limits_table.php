<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rate_limits')) {
            return;
        }

        Schema::create('rate_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('window_seconds')->default(60);
            $table->unsignedInteger('max_requests');
            $table->enum('scope', ['api', 'webhook_out', 'emails'])->default('api');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'scope']);
            $table->index(['scope', 'active']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('rate_limits');
        Schema::enableForeignKeyConstraints();
    }
};
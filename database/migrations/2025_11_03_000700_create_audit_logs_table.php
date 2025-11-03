<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type', 160);
            $table->unsignedBigInteger('entity_id');
            $table->enum('action', ['created', 'updated', 'deleted']);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'entity_type', 'entity_id'], 'audit_logs_company_entity_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

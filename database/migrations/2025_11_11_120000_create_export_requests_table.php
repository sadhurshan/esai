<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['full_data', 'audit_logs', 'custom']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index('expires_at');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('data_export_enabled')->default(false)->after('tax_engine_enabled');
            $table->unsignedInteger('export_history_days')->default(30)->after('data_export_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['data_export_enabled', 'export_history_days']);
        });

        Schema::dropIfExists('export_requests');
    }
};

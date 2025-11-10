<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_procedure_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('maintenance_procedure_id')->constrained('maintenance_procedures')->cascadeOnDelete();
            $table->unsignedInteger('frequency_value');
            $table->enum('frequency_unit', ['day', 'week', 'month', 'year', 'run_hours']);
            $table->dateTime('last_done_at')->nullable();
            $table->dateTime('next_due_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'maintenance_procedure_id']);
            $table->index(['maintenance_procedure_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_procedure_links');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_procedure_id')->constrained('maintenance_procedures')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->string('title', 191);
            $table->longText('instruction_md');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->json('attachments_json')->nullable();
            $table->timestamps();

            $table->unique(['maintenance_procedure_id', 'step_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_steps');
    }
};

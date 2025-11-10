<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('title', 191);
            $table->enum('category', ['preventive', 'corrective', 'inspection', 'calibration', 'safety']);
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->longText('instructions_md');
            $table->json('tools_json')->nullable();
            $table->json('safety_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_procedures');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_id')->nullable()->constrained('systems')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('tag', 64);
            $table->string('serial_no', 128)->nullable();
            $table->string('model_no', 128)->nullable();
            $table->string('manufacturer', 191)->nullable();
            $table->date('commissioned_at')->nullable();
            $table->enum('status', ['active', 'standby', 'retired', 'maintenance'])->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'tag']);
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'system_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('rfqs_count')->default(0);
            $table->unsignedInteger('quotes_count')->default(0);
            $table->unsignedInteger('pos_count')->default(0);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'date']);
            $table->index(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_snapshots');
    }
};

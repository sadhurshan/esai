<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('tag', 100);
            $table->string('normalized_tag', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['part_id', 'normalized_tag', 'deleted_at']);
            $table->index(['company_id', 'normalized_tag']);
            $table->index('normalized_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_tags');
    }
};

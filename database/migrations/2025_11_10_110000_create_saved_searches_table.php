<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('query', 255);
            $table->json('entity_types');
            $table->json('filters')->nullable();
            $table->string('tags', 191)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'user_id', 'name'], 'saved_searches_unique_user_name');
            $table->index(['company_id', 'user_id'], 'saved_searches_company_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};

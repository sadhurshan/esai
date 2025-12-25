<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_memories')) {
            return;
        }

        Schema::create('ai_chat_memories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('ai_chat_threads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->json('memory_json');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('thread_id');
            $table->index(['company_id', 'updated_at'], 'ai_chat_memories_company_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_memories');
    }
};

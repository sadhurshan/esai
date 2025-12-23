<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_chat_threads')) {
            Schema::create('ai_chat_threads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
                $table->string('title')->nullable();
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->text('thread_summary')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'last_message_at'], 'ai_chat_threads_company_last_msg_index');
                $table->index(['company_id', 'user_id'], 'ai_chat_threads_company_user_index');
            });
        }

        if (! Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('thread_id')->constrained('ai_chat_threads')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
                $table->enum('role', ['user', 'assistant', 'system', 'tool']);
                $table->longText('content_text');
                $table->json('content_json')->nullable();
                $table->json('citations_json')->nullable();
                $table->json('tool_calls_json')->nullable();
                $table->json('tool_results_json')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('status', 50)->default('pending'); // TODO: clarify status vocabulary per spec
                $table->timestamps();
                $table->softDeletes();

                $table->index(['thread_id', 'created_at'], 'ai_chat_messages_thread_created_index');
                $table->index(['company_id', 'user_id'], 'ai_chat_messages_company_user_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_threads');
    }
};

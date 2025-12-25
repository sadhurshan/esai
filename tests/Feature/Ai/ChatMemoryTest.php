<?php

use App\Models\AiChatMemory;
use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;

it('persists cross-session memory and injects it into chat payloads', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Ops updates',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $thread->appendMessage(AiChatMessage::ROLE_USER, [
        'user_id' => $user->id,
        'content_text' => 'Where is PO 44 right now?',
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);

    $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
        'user_id' => $user->id,
        'content_text' => 'PO 44 ships tomorrow with tracking 123.',
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('chatRespond')
        ->once()
        ->with(Mockery::on(function (array $payload): bool {
            $memoryTurns = data_get($payload, 'context.memory.turns', []);

            expect($memoryTurns)->toBeArray()->toHaveCount(3);
            expect($memoryTurns[0]['role'] ?? null)->toBe(AiChatMessage::ROLE_USER);
            expect($memoryTurns[1]['role'] ?? null)->toBe(AiChatMessage::ROLE_ASSISTANT);
            expect($memoryTurns[1]['content'] ?? '')->toContain('ships tomorrow');

            return true;
        }))
        ->andReturn([
            'status' => 'success',
            'message' => 'Chat response generated.',
            'data' => [
                'response' => [
                    'assistant_message_markdown' => 'Got it, I will keep monitoring.',
                    'citations' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ],
                'memory' => [
                    'thread_summary' => 'recap ready',
                ],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    $chatService = app(ChatService::class);
    $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Give me the latest status again', []);

    $memory = AiChatMemory::query()->where('thread_id', $thread->id)->first();

    expect($memory)->not->toBeNull();
    expect($memory->memory_json['turn_count'] ?? null)->toBe(3);
    expect($memory->last_message_id)->not->toBeNull();
});

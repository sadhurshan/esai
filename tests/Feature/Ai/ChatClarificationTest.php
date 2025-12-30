<?php

use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use Illuminate\Support\Arr;

it('asks for the RFQ title when the planner needs clarification', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Clarification thread',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('intentPlan')
        ->once()
        ->with(\Mockery::on(function (array $payload) use ($thread, $user): bool {
            expect($payload['prompt'] ?? null)->toBe('Draft an RFQ');
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);
            expect($payload['user_id'] ?? null)->toBe($user->id);

            return true;
        }))
        ->andReturn([
            'status' => 'success',
            'message' => 'clarification requested',
            'data' => [
                'tool' => 'clarification',
                'target_tool' => 'build_rfq_draft',
                'missing_args' => ['rfq_title'],
                'question' => 'What should be the title of the RFQ?',
                'args' => [],
            ],
        ]);

    $client->shouldNotReceive('planAction');
    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $result = $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Draft an RFQ');

    expect(data_get($result, 'response.type'))->toBe('clarification');
    expect(data_get($result, 'response.clarification.question'))->toContain('title');
    expect(data_get($result, 'assistant_message.content_text'))->toBe('What should be the title of the RFQ?');

    $pending = Arr::get($thread->fresh()->metadata_json, 'pending_clarification');
    expect($pending)->toBeArray()
        ->and($pending['tool'] ?? null)->toBe('build_rfq_draft')
        ->and($pending['missing_args'] ?? [])->toContain('rfq_title');
});

it('retries the planned action after receiving the clarification answer', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Clarification thread',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('intentPlan')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'clarification requested',
            'data' => [
                'tool' => 'clarification',
                'target_tool' => 'build_rfq_draft',
                'missing_args' => ['rfq_title'],
                'question' => 'What should be the title of the RFQ?',
                'args' => [],
            ],
        ]);

    $client->shouldReceive('planAction')
        ->once()
        ->with(\Mockery::on(function (array $payload): bool {
            expect($payload['action_type'] ?? null)->toBe('rfq_draft');
            expect($payload['inputs']['rfq_title'] ?? null)->toBe('Test RFQ');

            return true;
        }))
        ->andReturn([
            'status' => 'success',
            'message' => 'Action draft generated.',
            'data' => [
                'action_type' => 'rfq_draft',
                'summary' => 'RFQ ready',
                'rfq_title' => 'Test RFQ',
                'payload' => ['rfq_title' => 'Test RFQ'],
                'citations' => [],
                'warnings' => [],
            ],
            'errors' => [],
        ]);

    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Draft an RFQ');

    $pending = Arr::get($thread->fresh()->metadata_json, 'pending_clarification');
    expect($pending)->toBeArray();

    $result = $chatService->sendMessage(
        $thread->fresh(),
        $user->fresh(),
        'Call it Test RFQ',
        ['clarification' => ['id' => $pending['id'] ?? null]],
    );

    expect(data_get($result, 'response.type'))->toBe('draft_action');
    expect(data_get($result, 'response.draft.rfq_title'))->toBe('Test RFQ');
    expect(data_get($thread->fresh()->metadata_json, 'pending_clarification'))->toBeNull();
});

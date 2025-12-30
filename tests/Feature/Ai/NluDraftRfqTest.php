<?php

use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
    config()->set('ai_chat.permissions', []);
});

afterEach(function (): void {
    Mockery::close();
});

it('drafts RFQs end-to-end via the chat API with clarification follow-ups', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => null,
    ]);

    $company = Company::factory()
        ->for($plan)
        ->create([
            'status' => 'active',
        ]);

    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'NLU drafting flow',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->withArgs(function (array $payload) use ($thread, $user): bool {
            expect($payload['prompt'] ?? null)->toBe('Draft an RFQ named Rotar Blades for sourcing custom blades.');
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);
            expect($payload['user_id'] ?? null)->toBe($user->id);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'tool selected',
            'data' => [
                'tool' => 'build_rfq_draft',
                'args' => [
                    'rfq_title' => 'Rotar Blades',
                    'scope_summary' => 'sourcing custom blades',
                ],
            ],
        ]);

    $client->shouldReceive('planAction')
        ->once()
        ->withArgs(function (array $payload): bool {
            expect($payload['action_type'] ?? null)->toBe('rfq_draft');
            expect($payload['inputs']['rfq_title'] ?? null)->toBe('Rotar Blades');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Draft ready',
            'data' => [
                'action_type' => 'rfq_draft',
                'summary' => 'RFQ draft prepared.',
                'rfq_title' => 'Rotar Blades',
                'payload' => ['rfq_title' => 'Rotar Blades'],
                'citations' => [],
                'warnings' => [],
            ],
        ]);

    $client->shouldReceive('intentPlan')
        ->once()
        ->withArgs(function (array $payload) use ($thread): bool {
            expect($payload['prompt'] ?? null)->toBe('Draft an RFQ');
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'clarification required',
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
        ->withArgs(function (array $payload): bool {
            expect($payload['action_type'] ?? null)->toBe('rfq_draft');
            expect($payload['inputs']['rfq_title'] ?? null)->toBe('Test RFQ');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Draft ready',
            'data' => [
                'action_type' => 'rfq_draft',
                'summary' => 'RFQ draft prepared.',
                'rfq_title' => 'Test RFQ',
                'payload' => ['rfq_title' => 'Test RFQ'],
                'citations' => [],
                'warnings' => [],
            ],
        ]);

    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $route = "/api/v1/ai/chat/threads/{$thread->id}/send";

    $response = $this->postJson($route, [
        'message' => 'Draft an RFQ named Rotar Blades for sourcing custom blades.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.response.type', 'draft_action')
        ->assertJsonPath('data.response.draft.rfq_title', 'Rotar Blades');

    $clarificationResponse = $this->postJson($route, [
        'message' => 'Draft an RFQ',
    ]);

    $clarificationResponse->assertOk()
        ->assertJsonPath('data.response.type', 'clarification')
        ->assertJsonPath('data.response.clarification.missing_args.0', 'rfq_title');

    $clarificationId = $clarificationResponse->json('data.response.clarification.id');
    expect($clarificationId)->toBeString()->not->toBe('');

    $finalResponse = $this->postJson($route, [
        'message' => 'Call it Test RFQ',
        'context' => [
            'clarification' => ['id' => $clarificationId],
        ],
    ]);

    $finalResponse->assertOk()
        ->assertJsonPath('data.response.type', 'draft_action')
        ->assertJsonPath('data.response.draft.rfq_title', 'Test RFQ');

    expect(Arr::get($thread->fresh()->metadata_json, 'pending_clarification'))->toBeNull();
});

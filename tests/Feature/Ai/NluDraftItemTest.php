<?php

use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('drafts an inventory item via chat using the item planner tool', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => null,
    ]);

    $company = Company::factory()
        ->for($plan)
        ->create(['status' => 'active']);

    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Item drafting flow',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->withArgs(function (array $payload) use ($thread, $user): bool {
            expect($payload['prompt'] ?? null)->toBe('Create item Rotar Blades with hardened steel');
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);
            expect($payload['user_id'] ?? null)->toBe($user->id);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'tool selected',
            'data' => [
                'tool' => 'build_item_draft',
                'args' => [
                    'item_code' => 'ROTAR-100',
                    'name' => 'Rotar Blades',
                    'uom' => 'EA',
                ],
            ],
        ]);

    $client->shouldReceive('planAction')
        ->once()
        ->withArgs(function (array $payload): bool {
            expect($payload['action_type'] ?? null)->toBe('item_draft');
            expect($payload['inputs']['item_code'] ?? null)->toBe('ROTAR-100');
            expect($payload['inputs']['name'] ?? null)->toBe('Rotar Blades');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Draft ready',
            'data' => [
                'action_type' => 'item_draft',
                'summary' => 'Item draft prepared.',
                'payload' => [
                    'item_code' => 'ROTAR-100',
                    'name' => 'Rotar Blades',
                ],
                'citations' => [],
                'warnings' => [],
            ],
        ]);

    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/send", [
        'message' => 'Create item Rotar Blades with hardened steel',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.response.type', 'draft_action')
        ->assertJsonPath('data.response.draft.action_type', 'item_draft')
        ->assertJsonPath('data.response.draft.payload.item_code', 'ROTAR-100')
        ->assertJsonPath('data.response.draft.payload.name', 'Rotar Blades');
});

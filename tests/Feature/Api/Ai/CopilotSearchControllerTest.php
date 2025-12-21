<?php

use App\Models\AiEvent;
use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
    config()->set('ai.rate_limit.enabled', false);
});

afterEach(function (): void {
    \Mockery::close();
});

it('requires authentication for the copilot search endpoint', function (): void {
    $response = $this->postJson('/api/copilot/search', [
        'query' => 'Need document control guidance',
    ]);

    $response->assertUnauthorized();
});

it('prevents non admin users from triggering a document reindex', function (): void {
    Bus::fake();

    ['user' => $user] = provisionCopilotUser(role: 'buyer_requester');

    actingAs($user);

    $response = $this->postJson('/api/v1/admin/ai/reindex-document', [
        'doc_id' => 101,
        'doc_version' => 2,
    ]);

    $response->assertForbidden();

    Bus::assertNothingDispatched();
});

it('filters out search hits for documents the user cannot access and records an AI event', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotUser();

    $accessibleDoc = Document::factory()->for($company)->create([
        'visibility' => 'company',
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
    ]);

    $otherCompany = Company::factory()->create();
    $restrictedDoc = Document::factory()->for($otherCompany)->create([
        'visibility' => 'company',
        'documentable_type' => Company::class,
        'documentable_id' => $otherCompany->id,
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('search')
        ->once()
        ->withArgs(function (array $payload) use ($company): bool {
            expect($payload['company_id'])->toBe($company->id);
            expect($payload['query'])->toBe('document control policy');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Search ready.',
            'data' => [
                'hits' => [
                    [
                        'doc_id' => (string) $accessibleDoc->getKey(),
                        'doc_version' => '1',
                        'chunk_id' => 'chunk-allowed',
                        'score' => 0.95,
                        'title' => 'Allowed Doc',
                        'snippet' => 'Allowed snippet text',
                    ],
                    [
                        'doc_id' => (string) $restrictedDoc->getKey(),
                        'doc_version' => '1',
                        'chunk_id' => 'chunk-denied',
                        'score' => 0.42,
                        'title' => 'Denied Doc',
                        'snippet' => 'Denied snippet text',
                    ],
                ],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/copilot/search', [
        'query' => 'document control policy',
        'top_k' => 5,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.hits')
        ->assertJsonPath('data.hits.0.doc_id', (string) $accessibleDoc->getKey());

    expect(AiEvent::count())->toBe(1);

    $event = AiEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->feature)->toBe('search')
        ->and($event->status)->toBe(AiEvent::STATUS_SUCCESS);
});

it('records ai events for successful copilot answers and returns allowed citations', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotUser();

    $document = Document::factory()->for($company)->create([
        'visibility' => 'company',
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('answer')
        ->once()
        ->withArgs(function (array $payload) use ($company): bool {
            expect($payload['company_id'])->toBe($company->id);
            expect($payload['query'])->toBe('What is our QA policy?');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Answer ready.',
            'data' => [
                'answer' => '- QA policy summary',
                'citations' => [
                    [
                        'doc_id' => (string) $document->getKey(),
                        'doc_version' => '3',
                        'chunk_id' => 'chunk-123',
                        'score' => 0.88,
                        'snippet' => 'QA policy snippet',
                    ],
                ],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/copilot/answer', [
        'query' => 'What is our QA policy?',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.citations.0.doc_id', (string) $document->getKey());

    $event = AiEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->feature)->toBe('answer')
        ->and($event->status)->toBe(AiEvent::STATUS_SUCCESS)
        ->and($event->request_json['company_id'])->toBe($company->id)
        ->and($event->request_json['user_id'])->toBe($user->id)
        ->and($event->request_json['user_role'])->toBe($user->role);
});

it('forwards allow_general flag to the ai service when requested', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotUser();

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('answer')
        ->once()
        ->withArgs(function (array $payload) use ($company): bool {
            expect($payload['company_id'])->toBe($company->id);
            expect($payload['allow_general'])->toBeTrue();

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Answer ready.',
            'data' => [
                'answer_markdown' => 'General context answer',
                'citations' => [],
                'warnings' => ['Ungrounded'],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/copilot/answer', [
        'query' => 'What are the latest aerospace trends?',
        'allow_general' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.warnings.0', 'Ungrounded');
});

function provisionCopilotUser(string $role = 'buyer_admin'): array
{
    $plan = Plan::factory()->create([
        'code' => 'ai-plan-'.Str::lower(Str::random(8)),
        'price_usd' => 0,
    ]);

    $company = Company::factory()
        ->for($plan)
        ->create([
            'status' => 'active',
            'plan_code' => $plan->code,
        ]);

    $user = User::factory()->for($company)->create([
        'role' => $role,
    ]);

    return compact('user', 'company', 'plan');
}

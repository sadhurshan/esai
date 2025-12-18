<?php

use App\Exceptions\AiServiceUnavailableException;
use App\Models\AiEvent;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('requires authentication for the forecast endpoint', function (): void {
    $response = $this->postJson('/api/ai/forecast', [
        'part_id' => 1,
        'history' => [
            ['date' => now()->toDateString(), 'quantity' => 5],
        ],
        'horizon' => 14,
    ]);

    $response->assertUnauthorized();
});

it('requires authentication for the supplier risk endpoint', function (): void {
    $response = $this->postJson('/api/ai/supplier-risk', [
        'supplier' => ['id' => 5],
    ]);

    $response->assertUnauthorized();
});

it('forbids forecast requests when the user lacks inventory permissions', function (): void {
    ['user' => $user] = provisionAiUser(role: 'buyer_requester');

    $client = \Mockery::mock(AiClient::class);
    $client->shouldNotReceive('forecast');
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $payload = [
        'part_id' => 99,
        'history' => [
            ['date' => now()->subDay()->toDateString(), 'quantity' => 8],
        ],
        'horizon' => 21,
    ];

    $response = $this->postJson('/api/ai/forecast', $payload);

    $response->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to generate forecasts.')
        ->assertJsonPath('status', 'error');

    expect(AiEvent::count())->toBe(0);
});

it('forbids supplier risk requests when the user lacks sourcing access', function (): void {
    ['user' => $user] = provisionAiUser(role: 'finance');

    $client = \Mockery::mock(AiClient::class);
    $client->shouldNotReceive('supplierRisk');
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/ai/supplier-risk', [
        'supplier' => [
            'id' => 77,
            'company_id' => 1234,
            'name' => 'Acme Fabrication',
        ],
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to view supplier risk.')
        ->assertJsonPath('status', 'error');

    expect(AiEvent::count())->toBe(0);
});

it('returns forecast insights and records a success event', function (): void {
    ['user' => $user, 'company' => $company] = provisionAiUser();

    $expectedRequest = [
        'part_id' => 44,
        'history' => [
            ['date' => now()->subDays(2)->toDateString(), 'quantity' => 12.5],
        ],
        'horizon' => 30,
    ];

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('forecast')
        ->once()
        ->withArgs(fn (array $payload): bool => $payload === $expectedRequest)
        ->andReturn([
            'status' => 'success',
            'message' => 'Forecast ready.',
            'data' => [
                'safety_stock' => 18,
                'reorder_point' => 42,
            ],
            'errors' => null,
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/ai/forecast', array_merge($expectedRequest, [
        'entity_type' => 'inventory_item',
        'entity_id' => 420,
    ]));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.reorder_point', 42);

    $event = AiEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->company_id)->toBe($company->id)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->feature)->toBe('forecast')
        ->and($event->status)->toBe(AiEvent::STATUS_SUCCESS)
        ->and($event->entity_type)->toBe('inventory_item')
        ->and($event->entity_id)->toBe(420)
        ->and($event->request_json)->toMatchArray($expectedRequest)
        ->and($event->response_json['data']['reorder_point'])->toBe(42)
        ->and($event->latency_ms)->not->toBeNull();
});

it('returns supplier risk insights and records a success event', function (): void {
    ['user' => $user, 'company' => $company] = provisionAiUser();

    $riskPayload = [
        'supplier' => [
            'id' => 501,
            'company_id' => 9001,
            'name' => 'Nova Machining',
        ],
    ];

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('supplierRisk')
        ->once()
        ->withArgs(fn (array $payload): bool => $payload === $riskPayload)
        ->andReturn([
            'status' => 'success',
            'message' => 'Risk profile ready.',
            'data' => [
                'risk_score' => 0.42,
                'risk_category' => 'medium',
            ],
            'errors' => null,
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/ai/supplier-risk', array_merge($riskPayload, [
        'entity_type' => 'supplier_profile',
        'entity_id' => 9001,
    ]));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.risk_score', 0.42);

    $event = AiEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->company_id)->toBe($company->id)
        ->and($event->feature)->toBe('supplier_risk')
        ->and($event->status)->toBe(AiEvent::STATUS_SUCCESS)
        ->and($event->entity_type)->toBe('supplier_profile')
        ->and($event->entity_id)->toBe(9001)
        ->and($event->request_json)->toMatchArray($riskPayload)
        ->and($event->response_json['data']['risk_category'])->toBe('medium');
});

it('records an error event when the AI client is unavailable', function (): void {
    ['user' => $user, 'company' => $company] = provisionAiUser();

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('supplierRisk')
        ->once()
        ->andThrow(new AiServiceUnavailableException('AI service offline.'));

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $payload = [
        'supplier' => [
            'id' => 22,
            'company_id' => $company->id,
            'name' => 'Delta Forge',
        ],
        'entity_type' => 'rfq',
        'entity_id' => 555,
    ];

    $response = $this->postJson('/api/ai/supplier-risk', $payload);

    $response->assertStatus(503)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'AI service is unavailable.');

    $event = AiEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->company_id)->toBe($company->id)
        ->and($event->feature)->toBe('supplier_risk')
        ->and($event->status)->toBe(AiEvent::STATUS_ERROR)
        ->and($event->error_message)->toBe('AI service offline.')
        ->and($event->response_json)->toBeNull();
});

function provisionAiUser(string $role = 'buyer_admin'): array
{
    $plan = Plan::factory()->create([
        'code' => 'ai-plan-'.Str::random(8),
        'price_usd' => 0,
        'inventory_enabled' => true,
        'risk_scores_enabled' => true,
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

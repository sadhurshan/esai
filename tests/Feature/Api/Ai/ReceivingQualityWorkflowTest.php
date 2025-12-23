<?php

require_once __DIR__ . '/helpers.php';

use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
});

it('starts the receiving quality workflow template and persists steps', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planWorkflow')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-rqc',
                'status' => 'pending',
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'receiving_quality',
        'rfq_id' => null,
        'inputs' => [
            'receipts' => [
                ['receipt_id' => 'RC-1001'],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.workflow_id', 'wf-rqc');

    $workflow = AiWorkflow::query()->where('workflow_id', 'wf-rqc')->first();

    expect($workflow)->not->toBeNull()
        ->and($workflow?->workflow_type)->toBe('receiving_quality');
    expect(AiWorkflowStep::query()->where('workflow_id', 'wf-rqc')->count())->toBe(1);

    $step = AiWorkflowStep::query()->where('workflow_id', 'wf-rqc')->first();
    expect($step?->action_type)->toBe('receiving_quality');
});

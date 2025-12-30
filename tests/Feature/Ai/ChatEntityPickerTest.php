<?php

use App\Enums\AiChatToolCall;
use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

it('surfaces an entity picker when a workspace search has multiple matches', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Invoice lookup',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldNotReceive('chatContinue');
    $client->shouldNotReceive('intentPlan');
    $client->shouldNotReceive('planAction');
    app()->instance(AiClient::class, $client);

    $resolver = \Mockery::mock(WorkspaceToolResolver::class);
    $resolver->shouldReceive('resolveBatch')
        ->once()
        ->withArgs(function (int $companyId, array $calls) use ($company): bool {
            expect($companyId)->toBe($company->id);
            expect($calls)->toHaveCount(1);
            expect(data_get($calls, '0.tool_name'))->toBe(AiChatToolCall::SearchInvoices->value);

            return true;
        })
        ->andReturn([
            [
                'tool_name' => AiChatToolCall::SearchInvoices->value,
                'call_id' => 'call-search',
                'result' => [
                    'items' => [
                        [
                            'invoice_id' => 101,
                            'invoice_number' => 'INV-101',
                            'status' => 'open',
                            'total' => '$12,000.00',
                            'due_date' => now()->addDays(5)->toIso8601String(),
                            'supplier' => ['name' => 'Acme Metals'],
                        ],
                        [
                            'invoice_id' => 102,
                            'invoice_number' => 'INV-102',
                            'status' => 'pending',
                            'total' => '$18,500.00',
                            'due_date' => now()->addDays(10)->toIso8601String(),
                            'supplier' => ['name' => 'Zenith Fabrication'],
                        ],
                    ],
                    'meta' => [
                        'query' => 'INV-10',
                        'total_count' => 2,
                    ],
                ],
            ],
        ]);

    app()->instance(WorkspaceToolResolver::class, $resolver);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $result = $chatService->resolveTools($thread->fresh(), $user->fresh(), [[
        'tool_name' => AiChatToolCall::SearchInvoices->value,
        'call_id' => 'call-search',
        'arguments' => ['query' => 'INV-10'],
    ]]);

    expect(data_get($result, 'response.type'))->toBe('entity_picker');
    expect(data_get($result, 'response.entity_picker.candidates'))->toHaveCount(2);

    $pending = Arr::get($thread->fresh()->metadata_json, 'pending_entity_picker');
    expect($pending)->toBeArray()
        ->and($pending['target_tool'] ?? null)->toBe(AiChatToolCall::GetInvoice->value)
        ->and(Arr::get($pending, 'candidates.0.candidate_id'))->toBe('101');
});

it('executes the target tool after the user selects an entity', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Invoice follow-up',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('chatContinue')
        ->once()
        ->withArgs(function (array $payload) use ($thread, $user): bool {
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);
            expect($payload['user_id'] ?? null)->toBe($user->id);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'continued',
            'data' => [
                'response' => [
                    'assistant_message_markdown' => 'Invoice ready.',
                    'citations' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ],
                'memory' => [],
            ],
            'errors' => [],
        ]);
    $client->shouldNotReceive('intentPlan');
    $client->shouldNotReceive('planAction');

    app()->instance(AiClient::class, $client);

    $resolver = \Mockery::mock(WorkspaceToolResolver::class);
    $resolver->shouldReceive('resolveBatch')
        ->once()
        ->withArgs(function (int $companyId, array $calls): bool {
            return data_get($calls, '0.tool_name') === AiChatToolCall::SearchInvoices->value;
        })
        ->andReturn([
            [
                'tool_name' => AiChatToolCall::SearchInvoices->value,
                'call_id' => 'call-search',
                'result' => [
                    'items' => [
                        [
                            'invoice_id' => 501,
                            'invoice_number' => 'INV-501',
                            'status' => 'open',
                            'total' => '$9,250.00',
                            'due_date' => now()->addDays(2)->toIso8601String(),
                            'supplier' => ['name' => 'Atlas Aero'],
                        ],
                        [
                            'invoice_id' => 502,
                            'invoice_number' => 'INV-502',
                            'status' => 'pending',
                            'total' => '$4,120.00',
                            'due_date' => now()->addDays(4)->toIso8601String(),
                            'supplier' => ['name' => 'Beacon Plastics'],
                        ],
                    ],
                    'meta' => [
                        'query' => 'INV-50',
                        'total_count' => 2,
                    ],
                ],
            ],
        ]);

    $resolver->shouldReceive('resolveBatch')
        ->once()
        ->withArgs(function (int $companyId, array $calls): bool {
            expect(data_get($calls, '0.tool_name'))->toBe(AiChatToolCall::GetInvoice->value);
            expect(data_get($calls, '0.arguments.invoice_id'))->toBe(501);

            return true;
        })
        ->andReturn([
            [
                'tool_name' => AiChatToolCall::GetInvoice->value,
                'call_id' => 'call-get',
                'result' => [
                    'invoice' => [
                        'invoice_id' => 501,
                        'invoice_number' => 'INV-501',
                        'status' => 'open',
                    ],
                ],
            ],
        ]);

    app()->instance(WorkspaceToolResolver::class, $resolver);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $chatService->resolveTools($thread->fresh(), $user->fresh(), [[
        'tool_name' => AiChatToolCall::SearchInvoices->value,
        'call_id' => 'call-search',
        'arguments' => ['query' => 'INV-50'],
    ]]);

    $pending = Arr::get($thread->fresh()->metadata_json, 'pending_entity_picker');
    expect($pending)->toBeArray();
    $pickerId = $pending['id'];
    $candidateId = Arr::get($pending, 'candidates.0.candidate_id');

    $response = $chatService->sendMessage(
        $thread->fresh(),
        $user->fresh(),
        'Use the correct invoice',
        [
            'entity_picker' => [
                'id' => $pickerId,
                'candidate_id' => $candidateId,
            ],
        ],
    );

    expect(data_get($response, 'response.assistant_message_markdown'))->toBe('Invoice ready.');
    expect(Arr::get($thread->fresh()->metadata_json, 'pending_entity_picker'))->toBeNull();
});

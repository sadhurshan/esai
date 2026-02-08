<?php

use App\Models\AiActionDraft;
use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('flags invoice approvals as unsafe actions', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Unsafe action confirmations',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'no plan',
            'data' => null,
        ]);

    $client->shouldReceive('chatRespond')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'response' => [
                    'type' => 'draft_action',
                    'assistant_message_markdown' => 'I can mark the invoice as paid now.',
                    'citations' => [],
                    'draft' => [
                        'action_type' => AiActionDraft::TYPE_APPROVE_INVOICE,
                        'summary' => 'Mark invoice INV-1001 as paid.',
                        'payload' => [
                            'invoice_id' => 'INV-1001',
                            'payment_reference' => 'PAY-001',
                            'payment_amount' => 12500,
                            'payment_currency' => 'USD',
                            'payment_method' => 'ACH',
                        ],
                    ],
                ],
            ],
        ]);

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $response = $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Pay invoice INV-1001');

    expect(data_get($response, 'response.type'))->toBe('unsafe_action_confirmation');
    expect(data_get($response, 'response.unsafe_action.action_type'))->toBe(AiActionDraft::TYPE_APPROVE_INVOICE);
    expect(data_get($response, 'response.unsafe_action.impact'))->toContain('USD');

    $draft = AiActionDraft::query()->first();
    expect($draft)->not->toBeNull();
    expect($draft?->action_type)->toBe(AiActionDraft::TYPE_APPROVE_INVOICE);
    expect($draft?->status)->toBe(AiActionDraft::STATUS_DRAFTED);
});

it('flags payment drafts as unsafe actions', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Unsafe payment',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'no plan',
            'data' => null,
        ]);

    $client->shouldReceive('chatRespond')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'response' => [
                    'type' => 'draft_action',
                    'assistant_message_markdown' => 'I prepared a payment release.',
                    'citations' => [],
                    'draft' => [
                        'action_type' => AiActionDraft::TYPE_PAYMENT_DRAFT,
                        'summary' => 'Schedule payment PAY-002.',
                        'payload' => [
                            'invoice_id' => 'INV-2002',
                            'reference' => 'PAY-002',
                            'amount' => 9800,
                            'currency' => 'usd',
                            'payment_method' => 'wire',
                        ],
                    ],
                ],
            ],
        ]);

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $response = $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Release payment PAY-002');

    expect(data_get($response, 'response.type'))->toBe('unsafe_action_confirmation');
    expect(data_get($response, 'response.unsafe_action.action_type'))->toBe(AiActionDraft::TYPE_PAYMENT_DRAFT);
    expect(data_get($response, 'response.unsafe_action.acknowledgement'))->toContain('payment');
});

<?php

use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('executes planner steps sequentially when a multi-intent plan is returned', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Multi-intent planner',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'plan ready',
            'data' => [
                'tool' => 'plan',
                'steps' => [
                    [
                        'tool' => 'build_rfq_draft',
                        'args' => [
                            'rfq_title' => 'Rotor Program',
                            'scope_summary' => 'Sourcing support blades',
                        ],
                    ],
                    [
                        'tool' => 'build_supplier_onboard_draft',
                        'args' => [
                            'legal_name' => 'Orion Manufacturing',
                            'country' => 'US',
                            'email' => 'sourcing@orion.example',
                            'phone' => '+1-555-0100',
                            'payment_terms' => 'Net 30',
                            'tax_id' => '12-3456789',
                            'documents_needed' => [
                                ['type' => 'iso9001'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $actionCalls = [];

    $client->shouldReceive('planAction')
        ->twice()
        ->andReturnUsing(function (array $payload) use (&$actionCalls): array {
            $actionType = $payload['action_type'] ?? null;
            $actionCalls[] = $actionType;

            if ($actionType === 'rfq_draft') {
                expect($payload['inputs']['rfq_title'] ?? null)->toBe('Rotor Program');
            }

            if ($actionType === 'supplier_onboard_draft') {
                expect($payload['inputs']['legal_name'] ?? null)->toBe('Orion Manufacturing');
            }

            return [
                'status' => 'success',
                'message' => 'Draft ready',
                'data' => [
                    'action_type' => $actionType,
                    'summary' => sprintf('%s ready', (string) $actionType),
                    'payload' => ['action_type' => $actionType],
                    'citations' => [],
                    'warnings' => [],
                ],
            ];
        });

    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $response = $chatService->sendMessage($thread->fresh(), $user->fresh(), 'Draft an RFQ and onboard Orion Manufacturing');

    expect($actionCalls)->toBe(['rfq_draft', 'supplier_onboard_draft']);
    expect(data_get($response, 'response.type'))->toBe('draft_action');
    expect(data_get($response, 'response.draft.action_type'))->toBe('supplier_onboard_draft');

    $assistantCount = AiChatMessage::query()
        ->where('thread_id', $thread->id)
        ->where('role', AiChatMessage::ROLE_ASSISTANT)
        ->count();

    expect($assistantCount)->toBe(2);
});

<?php

require_once __DIR__ . '/helpers.php';

use App\Exceptions\AiChatException;
use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Services\Ai\AiClient;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
    config()->set('ai_chat.permissions', ['ai.workflows.run']);
});

afterEach(function (): void {
    Mockery::close();
});

it('creates chat threads for authorized users', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/chat/threads', [
        'title' => 'Supplier diligence',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.thread.title', 'Supplier diligence');

    expect(AiChatThread::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('sends a chat message and persists the assistant response', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Expedite RFQs',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $assistantPayload = [
        'type' => 'answer',
        'assistant_message_markdown' => 'Quotes 12 and 14 are within the target window.',
        'citations' => [],
        'suggested_quick_replies' => ['Compare winning quotes'],
        'draft' => null,
        'workflow' => null,
        'tool_calls' => null,
        'needs_human_review' => false,
        'confidence' => 0.78,
        'warnings' => [],
    ];

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('chatRespond')
        ->once()
        ->withArgs(function (array $payload) use ($thread): bool {
            expect((int) $payload['thread_id'])->toBe($thread->id);
            expect($payload['company_id'])->toBe($thread->company_id);
            expect($payload['messages'])->toHaveCount(1);
            expect($payload['messages'][0]['role'])->toBe('user');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'response' => $assistantPayload,
                'memory' => ['thread_summary' => 'Discussed expedite options.'],
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/send", [
        'message' => 'Which suppliers can still meet next Friday?',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.response.assistant_message_markdown', $assistantPayload['assistant_message_markdown'])
        ->assertJsonPath('data.assistant_message.role', AiChatMessage::ROLE_ASSISTANT)
        ->assertJsonPath('data.user_message.role', AiChatMessage::ROLE_USER);

    expect(AiChatMessage::query()->where('thread_id', $thread->id)->where('role', AiChatMessage::ROLE_ASSISTANT)->count())->toBe(1);
    expect($thread->refresh()->thread_summary)->toBe('Discussed expedite options.');
});

it('enables general answers for non workspace prompts', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'General questions',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('chatRespond')
        ->once()
        ->withArgs(function (array $payload) use ($thread): bool {
            expect((int) $payload['thread_id'])->toBe($thread->id);
            expect($payload['allow_general'])->toBeTrue();

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'response' => [
                    'type' => 'answer',
                    'assistant_message_markdown' => 'Here is what I know about aerospace AI.',
                ],
                'memory' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/send", [
        'message' => 'Are you AI?',
    ]);

    $response->assertOk()->assertJsonPath('data.response.type', 'answer');
});

it('blocks chat access when the user lacks the configured permissions', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser('buyer_requester');

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Unauthorized chat',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/send", [
        'message' => 'Check supplier awards',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'ai_chat_forbidden')
        ->assertJsonPath('message', 'You are not authorized to send chat messages.');
});

it('resolves workspace tool calls and continues the conversation', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Workspace loop',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $thread->appendMessage(AiChatMessage::ROLE_USER, [
        'user_id' => $user->id,
        'content_text' => 'Show me open RFQs for valves.',
        'content_json' => null,
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);

    $toolResults = [[
        'tool_name' => 'workspace.search_rfqs',
        'call_id' => 'call-1',
        'result' => ['items' => [['rfq_id' => 101, 'title' => 'Valve refresh']]],
    ]];

    $toolResolver = Mockery::mock(WorkspaceToolResolver::class);
    $toolResolver->shouldReceive('resolveBatch')
        ->once()
        ->with($thread->company_id, Mockery::type('array'))
        ->andReturn($toolResults);
    $this->app->instance(WorkspaceToolResolver::class, $toolResolver);

    $assistantPayload = [
        'type' => 'answer',
        'assistant_message_markdown' => 'Found 1 RFQ with matching specs.',
        'citations' => [],
        'suggested_quick_replies' => ['Show supplier awards'],
        'draft' => null,
        'workflow' => null,
        'tool_calls' => null,
        'needs_human_review' => false,
        'confidence' => 0.64,
        'warnings' => [],
    ];

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('chatContinue')
        ->once()
        ->withArgs(function (array $payload) use ($toolResults, $thread): bool {
            expect((int) $payload['thread_id'])->toBe($thread->id);
            expect($payload['tool_results'])->toMatchArray($toolResults);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'response' => $assistantPayload,
                'memory' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/tools/resolve", [
        'tool_calls' => [[
            'tool_name' => 'workspace.search_rfqs',
            'call_id' => 'call-1',
            'arguments' => ['query' => 'valve'],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.response.type', 'answer')
        ->assertJsonPath('data.assistant_message.role', AiChatMessage::ROLE_ASSISTANT);

    expect(AiChatMessage::query()->where('thread_id', $thread->id)->where('role', AiChatMessage::ROLE_TOOL)->count())->toBe(1);
});

it('returns a guided resolution when workspace tools fail', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Workspace failure',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $toolResolver = Mockery::mock(WorkspaceToolResolver::class);
    $toolResolver->shouldReceive('resolveBatch')
        ->once()
        ->withArgs(function ($companyId, array $calls) use ($thread): bool {
            expect($companyId)->toBe($thread->company_id);
            expect($calls[0]['tool_name'] ?? null)->toBe('workspace.get_rfq');

            return true;
        })
        ->andThrow(new AiChatException('Resolver failed.'));

    $helpResult = [[
        'tool_name' => 'workspace.help',
        'call_id' => 'help-fallback',
        'result' => [
            'summary' => 'Manual RFQ lookup steps.',
            'payload' => [
                'title' => 'RFQ lookup guide',
                'description' => 'Follow these steps to inspect RFQs manually.',
                'steps' => ['Open the RFQs workspace view.', 'Filter by the requested RFQ number.'],
                'cta_label' => 'Open RFQs',
                'cta_url' => 'https://example.test/rfqs',
            ],
            'citations' => [],
        ],
    ]];

    $toolResolver->shouldReceive('resolveBatch')
        ->once()
        ->withArgs(function ($companyId, array $calls) use ($thread): bool {
            expect($companyId)->toBe($thread->company_id);
            expect($calls[0]['tool_name'] ?? null)->toBe('workspace.help');

            return true;
        })
        ->andReturn($helpResult);

    $this->app->instance(WorkspaceToolResolver::class, $toolResolver);

    $client = Mockery::mock(AiClient::class);
    $client->shouldNotReceive('chatContinue');
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/chat/threads/{$thread->id}/tools/resolve", [
        'tool_calls' => [
            ['tool_name' => 'workspace.get_rfq', 'call_id' => 'c-1', 'arguments' => ['rfq_id' => 1]],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.response.type', 'guided_resolution')
        ->assertJsonPath('data.response.guided_resolution.title', 'RFQ lookup guide')
        ->assertJsonPath('data.assistant_message.content.guided_resolution.cta_url', 'https://example.test/rfqs')
        ->assertJsonPath('data.tool_message.role', AiChatMessage::ROLE_TOOL);

    expect(AiChatMessage::query()->where('thread_id', $thread->id)->where('role', AiChatMessage::ROLE_TOOL)->count())->toBe(1);
});

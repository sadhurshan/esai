<?php

namespace App\Services\Ai;

use App\Enums\AiChatToolCall;
use App\Exceptions\AiChatException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\AiActionDraft;
use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class ChatService
{
    private const STREAM_CACHE_PREFIX = 'ai_chat:stream:';
    private const GENERAL_GREETINGS = [
        'hi',
        'hello',
        'hey',
        'good morning',
        'good afternoon',
        'good evening',
        'greetings',
        'yo',
    ];
    private const WORKSPACE_KEYWORDS = [
        'rfq',
        'quote',
        'purchase order',
        'po ',
        'supplier',
        'inventory',
        'stock',
        'invoice',
        'pricing',
        'lead time',
        'award',
        'shipment',
    ];

    private int $historyLimit;
    private int $streamTokenTtl;
    private bool $streamingEnabled;
    private bool $memoryEnabled;
    private int $summaryMaxChars;
    private int $threadSummaryLimit;
    private int $toolRoundLimit;
    private int $toolCallLimit;
    private int $logPreviewLength;

    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
        private readonly WorkspaceToolResolver $toolResolver,
        private readonly CacheRepository $cache,
    ) {
        $this->historyLimit = max(5, (int) config('ai_chat.history_limit', 30));
        $this->streamTokenTtl = max(30, (int) config('ai_chat.streaming.token_ttl', 180));
        $this->streamingEnabled = (bool) config('ai_chat.streaming.enabled', false);
        $this->memoryEnabled = (bool) config('ai_chat.memory.enabled', true);
        $this->summaryMaxChars = max(200, (int) config('ai_chat.memory.summary_max_chars', 1800));
        $this->threadSummaryLimit = max(500, (int) config('ai_chat.memory.thread_summary_limit', 5000));
        $this->toolRoundLimit = max(1, (int) config('ai_chat.tooling.max_rounds_per_message', 3));
        $this->toolCallLimit = max(1, (int) config('ai_chat.tooling.max_calls_per_request', 3));
        $this->logPreviewLength = max(60, (int) config('ai_chat.logs.message_preview_length', 240));
    }

    /**
     * @param array<string, mixed> $context
     * @return array{tool_message:AiChatMessage,assistant_message:AiChatMessage,response:array<string, mixed>}
     *
     * @throws AiChatException
     * @throws AiServiceUnavailableException
     */
    public function resolveTools(AiChatThread $thread, User $user, array $toolCalls, array $context = []): array
    {
        if ($toolCalls === []) {
            throw new AiChatException('At least one tool call is required.');
        }

        if (count($toolCalls) > $this->toolCallLimit) {
            throw new AiChatException('Too many workspace tool calls requested in a single turn.');
        }

        $this->ensureToolRoundLimit($thread);

        $structuredContext = $this->normalizeContext($context);

        $toolResults = $this->dispatchWorkspaceTools($thread, $user, $toolCalls, $structuredContext);

        $toolMessage = $thread->appendMessage(AiChatMessage::ROLE_TOOL, [
            'user_id' => $user->id,
            'content_text' => 'Workspace tool results ready.',
            'content_json' => ['tool_results' => $toolResults],
            'tool_results_json' => $toolResults,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $payload = [
            'thread_id' => (string) $thread->id,
            'company_id' => $thread->company_id,
            'user_id' => $user->id,
            'user_id_hash' => $this->hashUser($user),
            'messages' => $this->conversationHistory($thread),
            'tool_results' => $toolResults,
            'context' => $this->encodeContextPayload($structuredContext),
            'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
        ];

        $startedAt = microtime(true);

        try {
            $response = $this->client->chatContinue($payload);
        } catch (AiServiceUnavailableException $exception) {
            $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, null, null, $exception->getMessage());

            throw $exception;
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, $latency, null, $response['message'] ?? 'Chat service error.');

            throw new AiChatException($response['message'] ?? 'Failed to continue chat response.', $response['errors'] ?? null);
        }

        [$rawAssistantPayload, $memoryPayload] = $this->unpackChatResponse($response['data']);

        /** @var array<string, mixed> $assistantPayload */
        $assistantPayload = $this->attachDraftSnapshot($thread, $user, $rawAssistantPayload, null, $structuredContext);

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => $this->normalizeList($assistantPayload['tool_calls'] ?? []),
            'tool_results_json' => $this->normalizeList($assistantPayload['tool_results'] ?? $toolResults ?? []),
            'latency_ms' => $latency,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->applyThreadMemory($thread, $memoryPayload);

        $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, $latency, $assistantPayload, null);

        return [
            'tool_message' => $toolMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    public function appendSystemMessage(AiChatThread $thread, User $user, string $content, array $payload = []): AiChatMessage
    {
        return $thread->appendMessage(AiChatMessage::ROLE_SYSTEM, [
            'user_id' => $user->id,
            'content_text' => $content,
            'content_json' => $payload === [] ? null : $payload,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);
    }

    public function createThread(int $companyId, User $user, ?string $title = null): AiChatThread
    {
        $thread = AiChatThread::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'title' => $title,
            'status' => AiChatThread::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        $this->recorder->record(
            companyId: $companyId,
            userId: $user->id,
            feature: 'ai_chat_thread_create',
            requestPayload: [
                'title' => $title,
            ],
            responsePayload: [
                'thread_id' => $thread->id,
                'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
            ],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: 'ai_chat_thread',
            entityId: $thread->id,
        );

        return $thread;
    }

    public function getThreadWithMessages(int $threadId, int $companyId, ?int $limit = null): ?AiChatThread
    {
        $limit = $limit ?? $this->historyLimit;

        $thread = AiChatThread::query()
            ->forCompany($companyId)
            ->whereKey($threadId)
            ->with(['messages' => function ($query) use ($limit): void {
                $query->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit($limit);
            }])
            ->first();

        if (! $thread instanceof AiChatThread) {
            return null;
        }

        $messages = $thread->getRelation('messages');
        if ($messages instanceof Collection) {
            $thread->setRelation('messages', $messages->sortBy('id')->values());
        }

        return $thread;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{user_message:AiChatMessage,assistant_message:AiChatMessage,response:array<string, mixed>}
     *
     * @throws AiChatException
     * @throws AiServiceUnavailableException
     */
    public function sendMessage(AiChatThread $thread, User $user, string $message, array $context = []): array
    {
        $structuredContext = $this->normalizeContext($context);
        $allowGeneral = $this->shouldAllowGeneralAnswers($message, $structuredContext);

        $userMessage = $thread->appendMessage(AiChatMessage::ROLE_USER, [
            'user_id' => $user->id,
            'content_text' => $message,
            'content_json' => $structuredContext === [] ? null : ['context' => $structuredContext],
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $payload = [
            'thread_id' => (string) $thread->id,
            'company_id' => $thread->company_id,
            'user_id' => $user->id,
            'user_id_hash' => $this->hashUser($user),
            'messages' => $this->conversationHistory($thread),
            'context' => $this->encodeContextPayload($structuredContext),
            'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
            'allow_general' => $allowGeneral,
        ];

        $startedAt = microtime(true);

        try {
            $response = $this->client->chatRespond($payload);
        } catch (AiServiceUnavailableException $exception) {
            $this->recordChatEvent($thread, $user, $message, $structuredContext, null, null, $exception->getMessage());

            throw $exception;
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            $this->recordChatEvent($thread, $user, $message, $structuredContext, $latency, null, $response['message'] ?? 'Chat service error.');

            throw new AiChatException($response['message'] ?? 'Failed to process chat response.', $response['errors'] ?? null);
        }

        [$rawAssistantPayload, $memoryPayload] = $this->unpackChatResponse($response['data']);

        /** @var array<string, mixed> $assistantPayload */
        $assistantPayload = $this->attachDraftSnapshot($thread, $user, $rawAssistantPayload, $message, $structuredContext);

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => $this->normalizeList($assistantPayload['tool_calls'] ?? []),
            'tool_results_json' => $this->normalizeList($assistantPayload['tool_results'] ?? []),
            'latency_ms' => $latency,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->applyThreadMemory($thread, $memoryPayload);

        $this->recordChatEvent($thread, $user, $message, $structuredContext, $latency, $assistantPayload, null);

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{stream_token:string,expires_in:int,user_message:AiChatMessage}
     *
     * @throws AiChatException
     */
    public function prepareStream(AiChatThread $thread, User $user, string $message, array $context = []): array
    {
        if (! $this->streamingEnabled) {
            throw new AiChatException('Streaming is not enabled for this workspace.');
        }

        $structuredContext = $this->normalizeContext($context);
        $allowGeneral = $this->shouldAllowGeneralAnswers($message, $structuredContext);

        $userMessage = $thread->appendMessage(AiChatMessage::ROLE_USER, [
            'user_id' => $user->id,
            'content_text' => $message,
            'content_json' => $structuredContext === [] ? null : ['context' => $structuredContext],
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $payload = [
            'thread_id' => (string) $thread->id,
            'company_id' => $thread->company_id,
            'user_id' => $user->id,
            'user_id_hash' => $this->hashUser($user),
            'messages' => $this->conversationHistory($thread),
            'context' => $this->encodeContextPayload($structuredContext),
            'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
            'allow_general' => $allowGeneral,
        ];

        $token = $this->generateStreamToken();
        $cachePayload = [
            'thread_id' => $thread->id,
            'company_id' => $thread->company_id,
            'user_id' => $user->id,
            'payload' => $payload,
            'context' => $structuredContext,
            'latest_prompt' => $message,
            'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
        ];

        $this->cache->put(
            $this->streamCacheKey($token),
            $cachePayload,
            now()->addSeconds($this->streamTokenTtl)
        );

        return [
            'stream_token' => $token,
            'expires_in' => $this->streamTokenTtl,
            'user_message' => $userMessage->fresh(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AiChatException
     */
    public function claimStreamSession(AiChatThread $thread, User $user, string $token): array
    {
        if ($token === '') {
            throw new AiChatException('Stream token is required.');
        }

        $session = $this->cache->pull($this->streamCacheKey($token));

        if (! is_array($session)) {
            throw new AiChatException('Stream token is invalid or has expired.');
        }

        $threadMatches = (int) ($session['thread_id'] ?? 0) === $thread->id;
        $companyMatches = (int) ($session['company_id'] ?? 0) === $thread->company_id;
        $userMatches = (int) ($session['user_id'] ?? 0) === $user->id;

        if (! ($threadMatches && $companyMatches && $userMatches)) {
            throw new AiChatException('Stream token does not match this thread.');
        }

        return $session;
    }

    /**
     * @param array<string, mixed> $session
     * @param callable(string):void $emitter
     */
    public function runStreamSession(AiChatThread $thread, User $user, array $session, callable $emitter): void
    {
        $payload = $session['payload'] ?? null;
        if (! is_array($payload)) {
            $emitter($this->buildSseFrame('error', ['message' => 'Streaming payload missing.']));

            return;
        }

        $structuredContext = is_array($session['context'] ?? null) ? $session['context'] : [];
        $latestPrompt = is_string($session['latest_prompt'] ?? null) ? $session['latest_prompt'] : null;

        $assistantPayload = null;
        $accumulatedMarkdown = '';
        $startedAt = microtime(true);
        $memoryPayload = null;

        try {
            $this->client->chatRespondStream($payload, function (array $event) use (&$assistantPayload, &$accumulatedMarkdown, &$memoryPayload, $emitter): void {
                $emitter($event['frame']);

                if ($event['event'] === 'delta') {
                    $delta = $event['data']['text'] ?? '';
                    if (is_string($delta)) {
                        $accumulatedMarkdown .= $delta;
                    }
                }

                if ($event['event'] === 'complete' && isset($event['data']['response']) && is_array($event['data']['response'])) {
                    $assistantPayload = $event['data']['response'];
                    $memory = $event['data']['memory'] ?? null;
                    $memoryPayload = is_array($memory) ? $memory : null;
                }
            });
        } catch (Throwable $exception) {
            $emitter($this->buildSseFrame('error', ['message' => 'Streaming failed. Please retry shortly.']));
            $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, null, null, $exception->getMessage());
            report($exception);

            return;
        }

        if (! is_array($assistantPayload)) {
            $emitter($this->buildSseFrame('error', ['message' => 'Streaming finished without a response.']));
            $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, null, null, 'Streaming response incomplete.');

            return;
        }

        if (! isset($assistantPayload['assistant_message_markdown']) || ! is_string($assistantPayload['assistant_message_markdown'])) {
            $assistantPayload['assistant_message_markdown'] = $accumulatedMarkdown;
        }

        $assistantPayload = $this->attachDraftSnapshot($thread, $user, $assistantPayload, $latestPrompt, $structuredContext);

        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => $this->normalizeList($assistantPayload['tool_calls'] ?? []),
            'tool_results_json' => $this->normalizeList($assistantPayload['tool_results'] ?? []),
            'latency_ms' => $latency,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, $latency, $assistantPayload, null);

        $this->applyThreadMemory($thread, $memoryPayload);

        $emitter($this->buildSseFrame('final', [
            'assistant_message' => $this->serializeMessage($assistantMessage->fresh()),
            'response' => $assistantPayload,
            'latency_ms' => $latency,
        ]));
    }

    /**
     * @return list<array{role:string,content:?string,content_json:array<string, mixed>|null,created_at:?string}>
     */
    private function conversationHistory(AiChatThread $thread, ?int $limit = null): array
    {
        $limit = $limit ?? $this->historyLimit;

        return AiChatMessage::query()
            ->where('thread_id', $thread->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (AiChatMessage $message): array => [
                'role' => $message->role,
                'content' => $this->resolveMessageContent($message),
                'content_json' => is_array($message->content_json) ? $message->content_json : null,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ])
            ->all();
    }

    private function normalizeThreadSummary(?string $summary): ?string
    {
        if (! is_string($summary)) {
            return null;
        }

        $trimmed = trim($summary);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed, 'UTF-8') > $this->threadSummaryLimit) {
            return mb_substr($trimmed, 0, $this->threadSummaryLimit, 'UTF-8');
        }

        return $trimmed;
    }

    private function resolveMessageContent(AiChatMessage $message): string
    {
        $text = trim((string) ($message->content_text ?? ''));

        if ($text !== '') {
            return $text;
        }

        $payload = is_array($message->content_json) ? $message->content_json : [];

        $fallbackKeys = [
            'assistant_message_markdown',
            'content',
            'text',
            'body',
        ];

        foreach ($fallbackKeys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (isset($payload['context'])) {
            $encoded = json_encode($payload['context'], JSON_UNESCAPED_SLASHES);

            if (is_string($encoded) && $encoded !== 'null') {
                return $encoded;
            }
        }

        return '[no content]';
    }

    private function generateStreamToken(): string
    {
        return sprintf('%s-%s', Str::uuid()->toString(), Str::random(24));
    }

    private function streamCacheKey(string $token): string
    {
        return self::STREAM_CACHE_PREFIX . $token;
    }

    private function buildSseFrame(string $event, array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return sprintf("event: %s\ndata: %s\n\n", $event, $json !== false ? $json : '{}');
    }

    private function serializeMessage(AiChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'thread_id' => $message->thread_id,
            'user_id' => $message->user_id,
            'role' => $message->role,
            'content_text' => $message->content_text,
            'content' => is_array($message->content_json) ? $message->content_json : null,
            'citations' => is_array($message->citations_json) ? $message->citations_json : [],
            'tool_calls' => is_array($message->tool_calls_json) ? $message->tool_calls_json : [],
            'tool_results' => is_array($message->tool_results_json) ? $message->tool_results_json : [],
            'latency_ms' => $message->latency_ms,
            'status' => $message->status,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'updated_at' => optional($message->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:array<string, mixed>,1:array<string, mixed>|null}
     */
    private function unpackChatResponse(array $payload): array
    {
        if (isset($payload['response']) && is_array($payload['response'])) {
            $memory = isset($payload['memory']) && is_array($payload['memory']) ? $payload['memory'] : null;

            return [$payload['response'], $memory];
        }

        return [$payload, null];
    }

    private function applyThreadMemory(AiChatThread $thread, ?array $memory): void
    {
        if (! $this->memoryEnabled || ! is_array($memory)) {
            return;
        }

        $summary = $memory['thread_summary'] ?? null;

        if (! is_string($summary)) {
            return;
        }

        $normalized = trim(mb_substr($summary, 0, $this->summaryMaxChars));

        if ($normalized === '' || $normalized === (string) $thread->thread_summary) {
            return;
        }

        $thread->forceFill(['thread_summary' => $normalized])->save();
    }

    private function ensureToolRoundLimit(AiChatThread $thread): void
    {
        if ($this->toolRoundLimit < 1) {
            return;
        }

        $recentMessages = AiChatMessage::query()
            ->where('thread_id', $thread->id)
            ->orderByDesc('id')
            ->limit($this->toolRoundLimit * 4)
            ->get();

        $rounds = 0;

        foreach ($recentMessages as $message) {
            if ($message->role === AiChatMessage::ROLE_TOOL) {
                $rounds++;

                continue;
            }

            if ($message->role === AiChatMessage::ROLE_USER) {
                break;
            }
        }

        if ($rounds >= $this->toolRoundLimit) {
            throw new AiChatException('Copilot already attempted multiple workspace lookups for this request. Refine your question and try again.');
        }
    }

    private function sanitizeContextForLogging(array $context): array
    {
        $hasContext = isset($context['context']) && is_array($context['context']) && $context['context'] !== [];
        $uiMode = isset($context['ui_mode']) && is_string($context['ui_mode']) ? trim($context['ui_mode']) : null;
        $attachmentCount = isset($context['attachments']) && is_array($context['attachments']) ? count($context['attachments']) : 0;

        return array_filter([
            'has_structured_context' => $hasContext ? true : null,
            'ui_mode' => $uiMode !== '' ? $uiMode : null,
            'attachment_count' => $attachmentCount > 0 ? $attachmentCount : null,
        ], static fn ($value) => $value !== null);
    }

    private function sanitizeToolCalls(array $toolCalls): array
    {
        $sanitized = [];

        foreach ($toolCalls as $call) {
            if (! is_array($call)) {
                continue;
            }

            $entry = array_filter([
                'tool_name' => isset($call['tool_name']) ? (string) $call['tool_name'] : null,
                'call_id' => isset($call['call_id']) ? (string) $call['call_id'] : null,
            ], static fn ($value) => $value !== null && $value !== '');

            if ($entry !== []) {
                $sanitized[] = $entry;
            }
        }

        return $sanitized;
    }

    private function formatMessagePreview(string $message): string
    {
        $collapsed = preg_replace('/\s+/', ' ', $message) ?? $message;

        return Str::limit(trim($collapsed), $this->logPreviewLength);
    }

    private function hashUser(User $user): string
    {
        $identifier = $user->email;

        if ($identifier === null || $identifier === '') {
            $authId = $user->getAuthIdentifier();
            $identifier = is_string($authId) ? $authId : (string) $user->getKey();
        }

        $appKey = (string) config('app.key');

        return hash('sha256', sprintf('%s|%s', $appKey, $identifier));
    }

    /**
     * @param list<array{tool_name:string,call_id:string,arguments?:array<string,mixed>}> $toolCalls
     * @param array<string, mixed> $context
     * @return list<array{tool_name:string,call_id:string,result:array<string,mixed>|null}>
     */
    private function dispatchWorkspaceTools(
        AiChatThread $thread,
        User $user,
        array $toolCalls,
        array $context
    ): array {
        $resultsByIndex = [];
        $batchedCalls = [];
        $batchedIndexes = [];

        foreach ($toolCalls as $index => $call) {
            $toolName = (string) ($call['tool_name'] ?? '');
            $callId = (string) ($call['call_id'] ?? Str::uuid()->toString());
            $arguments = isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : [];

            if ($toolName === AiChatToolCall::AwardQuote->value) {
                $resultsByIndex[$index] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'result' => $this->resolveAwardQuoteTool($thread, $user, $arguments, $context),
                ];

                continue;
            }

            if ($toolName === AiChatToolCall::InvoiceDraft->value) {
                $resultsByIndex[$index] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'result' => $this->resolveInvoiceDraftTool($thread, $user, $arguments, $context),
                ];

                continue;
            }

            if (in_array($toolName, [
                AiChatToolCall::ReviewRfq->value,
                AiChatToolCall::ReviewQuote->value,
                AiChatToolCall::ReviewPo->value,
                AiChatToolCall::ReviewInvoice->value,
            ], true)) {
                $resultsByIndex[$index] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'result' => $this->resolveReviewTool($thread, $user, $toolName, $arguments, $context),
                ];

                continue;
            }

            $batchedCalls[] = [
                'tool_name' => $toolName,
                'call_id' => $callId,
                'arguments' => $arguments,
            ];
            $batchedIndexes[] = $index;
        }

        if ($batchedCalls !== []) {
            $batchedResults = $this->toolResolver->resolveBatch($thread->company_id, $batchedCalls);

            foreach ($batchedIndexes as $offset => $originalIndex) {
                if (! isset($batchedResults[$offset])) {
                    continue;
                }

                $resultsByIndex[$originalIndex] = $batchedResults[$offset];
            }
        }

        ksort($resultsByIndex);

        return array_values(array_filter($resultsByIndex));
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveAwardQuoteTool(
        AiChatThread $thread,
        User $user,
        array $arguments,
        array $context
    ): array {
        $inputs = $this->extractAwardQuoteInputs($arguments, $context);

        $requestPayload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => $this->normalizeToolContextBlocks($arguments['context'] ?? $context['context'] ?? []),
            'inputs' => $inputs,
        ];

        try {
            $response = $this->client->buildAwardQuoteTool($requestPayload);
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Award quote drafting service is unavailable. Please try again later.', null, 0, $exception);
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to build award quote recommendation.', $response['errors'] ?? null);
        }

        $data = $response['data'];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Award quote draft generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->normalizeList($data['citations'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveInvoiceDraftTool(
        AiChatThread $thread,
        User $user,
        array $arguments,
        array $context
    ): array {
        $inputs = $this->extractInvoiceDraftInputs($arguments, $context);

        $requestPayload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => $this->normalizeToolContextBlocks($arguments['context'] ?? $context['context'] ?? []),
            'inputs' => $inputs,
        ];

        try {
            $response = $this->client->buildInvoiceDraftTool($requestPayload);
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Invoice drafting service is unavailable. Please try again later.', null, 0, $exception);
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to build invoice draft.', $response['errors'] ?? null);
        }

        $data = $response['data'];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Invoice draft generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->normalizeList($data['citations'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveReviewTool(
        AiChatThread $thread,
        User $user,
        string $toolName,
        array $arguments,
        array $context
    ): array {
        $inputs = $this->buildReviewInputs($toolName, $arguments, $context);

        $requestPayload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => $this->normalizeToolContextBlocks($arguments['context'] ?? $context['context'] ?? []),
            'inputs' => $inputs,
        ];

        try {
            $response = match ($toolName) {
                AiChatToolCall::ReviewRfq->value => $this->client->reviewRfqTool($requestPayload),
                AiChatToolCall::ReviewQuote->value => $this->client->reviewQuoteTool($requestPayload),
                AiChatToolCall::ReviewPo->value => $this->client->reviewPoTool($requestPayload),
                AiChatToolCall::ReviewInvoice->value => $this->client->reviewInvoiceTool($requestPayload),
                default => null,
            };
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Review helper service is unavailable. Please try again later.', null, 0, $exception);
        }

        if (! is_array($response) || $response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to build review checklist.', $response['errors'] ?? null);
        }

        $data = $response['data'];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Review checklist generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->normalizeList($data['citations'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function extractAwardQuoteInputs(array $arguments, array $context): array
    {
        $rfqContext = is_array($context['context'] ?? null) ? $context['context'] : [];
        $rfqId = $this->stringValue($arguments['rfq_id'] ?? $rfqContext['rfq_id'] ?? null);

        $selectedQuote = isset($arguments['selected_quote']) && is_array($arguments['selected_quote'])
            ? $arguments['selected_quote']
            : [];
        $selectedQuoteId = $this->stringValue($arguments['selected_quote_id'] ?? ($selectedQuote['id'] ?? null));

        $supplierBlock = isset($arguments['supplier']) && is_array($arguments['supplier'])
            ? $arguments['supplier']
            : [];
        if ($supplierBlock === [] && isset($selectedQuote['supplier']) && is_array($selectedQuote['supplier'])) {
            $supplierBlock = $selectedQuote['supplier'];
        }

        $supplierId = $this->stringValue(
            $arguments['supplier_id']
            ?? ($selectedQuote['supplier_id'] ?? null)
            ?? ($supplierBlock['supplier_id'] ?? $supplierBlock['id'] ?? null)
        );

        if ($rfqId === null || $selectedQuoteId === null) {
            throw new AiChatException('Award quote tool requires rfq_id and selected_quote_id arguments.');
        }

        $inputs = [
            'rfq_id' => $rfqId,
            'selected_quote_id' => $selectedQuoteId,
        ];

        if ($supplierId !== null) {
            $inputs['supplier_id'] = $supplierId;
        }

        if ($selectedQuote !== []) {
            $inputs['selected_quote'] = $selectedQuote;
        }

        if ($supplierBlock !== []) {
            $inputs['supplier'] = $supplierBlock;
        }

        foreach (['justification', 'delivery_date'] as $key) {
            $value = $this->stringValue($arguments[$key] ?? null);
            if ($value !== null) {
                $inputs[$key] = $value;
            }
        }

        if (isset($arguments['terms']) && is_array($arguments['terms'])) {
            $terms = [];
            foreach ($arguments['terms'] as $term) {
                $value = $this->stringValue($term);
                if ($value !== null) {
                    $terms[] = $value;
                }
            }

            if ($terms !== []) {
                $inputs['terms'] = $terms;
            }
        }

        return $inputs;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function extractInvoiceDraftInputs(array $arguments, array $context): array
    {
        $poContext = is_array($context['context'] ?? null) ? $context['context'] : [];
        $poId = $this->stringValue(
            $arguments['po_id']
            ?? $poContext['po_id']
            ?? $poContext['purchase_order_id']
            ?? $poContext['po_number']
        );

        if ($poId === null) {
            throw new AiChatException('Invoice draft tool requires po_id argument.');
        }

        $inputs = ['po_id' => $poId];

        foreach (['invoice_date', 'due_date', 'notes'] as $key) {
            $value = $this->stringValue($arguments[$key] ?? $poContext[$key] ?? null);
            if ($value !== null) {
                $inputs[$key] = $value;
            }
        }

        $lineItems = $this->extractInvoiceDraftLineItems($arguments['line_items'] ?? null, $poContext['line_items'] ?? null);
        if ($lineItems !== []) {
            $inputs['line_items'] = $lineItems;
        }

        return $inputs;
    }

    /**
     * @param mixed $primary
     * @param mixed $fallback
     * @return list<array{description:string,qty:float,unit_price:float,tax_rate:float}>
     */
    private function extractInvoiceDraftLineItems(mixed $primary, mixed $fallback): array
    {
        $source = null;

        if (is_array($primary)) {
            $source = $primary;
        } elseif (is_array($fallback)) {
            $source = $fallback;
        }

        if ($source === null || ! array_is_list($source)) {
            return [];
        }

        $items = [];

        foreach ($source as $index => $rawLine) {
            if (! is_array($rawLine)) {
                continue;
            }

            $description = $this->stringValue($rawLine['description'] ?? $rawLine['item'] ?? $rawLine['part_number'] ?? null)
                ?? sprintf('Line %d', $index + 1);
            $quantity = $this->floatValue($rawLine['qty'] ?? $rawLine['quantity']) ?? 1.0;
            $unitPrice = $this->floatValue($rawLine['unit_price'] ?? $rawLine['price']) ?? 0.0;
            $taxRate = $this->floatValue($rawLine['tax_rate'] ?? $rawLine['tax']) ?? 0.0;

            $items[] = [
                'description' => $description,
                'qty' => max(0.01, $quantity),
                'unit_price' => max(0.0, $unitPrice),
                'tax_rate' => max(0.0, $taxRate),
            ];

            if (count($items) >= 25) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildReviewInputs(string $toolName, array $arguments, array $context): array
    {
        $inputs = is_array($arguments) ? $arguments : [];
        if (isset($inputs['context'])) {
            unset($inputs['context']);
        }

        $contextPayload = isset($context['context']) && is_array($context['context']) ? $context['context'] : [];

        $candidateKeys = match ($toolName) {
            AiChatToolCall::ReviewRfq->value => ['rfq_id'],
            AiChatToolCall::ReviewQuote->value => ['quote_id'],
            AiChatToolCall::ReviewPo->value => ['po_id', 'purchase_order_id'],
            AiChatToolCall::ReviewInvoice->value => ['invoice_id', 'invoice_number', 'id'],
            default => [],
        };

        $identifier = $this->extractReviewIdentifier($inputs, $contextPayload, $candidateKeys);

        if ($identifier === null) {
            $label = match ($toolName) {
                AiChatToolCall::ReviewRfq->value => 'rfq_id',
                AiChatToolCall::ReviewQuote->value => 'quote_id',
                AiChatToolCall::ReviewPo->value => 'po_id',
                AiChatToolCall::ReviewInvoice->value => 'invoice_id',
                default => 'record id',
            };

            throw new AiChatException(sprintf('Review tool requires %s argument.', $label));
        }

        if ($candidateKeys !== []) {
            $inputs[$candidateKeys[0]] = $identifier;
        }

        return $inputs;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $contextPayload
     * @param list<string> $candidates
     */
    private function extractReviewIdentifier(array $arguments, array $contextPayload, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = $this->stringValue($arguments[$candidate] ?? $contextPayload[$candidate] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function floatValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && $value !== '') {
            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    private function normalizeToolContextBlocks(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            return [$value];
        }

        $blocks = [];

        foreach ($value as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blocks[] = $block;

            if (count($blocks) >= 5) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = array_filter([
            'context' => isset($context['context']) && is_array($context['context']) ? $context['context'] : [],
            'ui_mode' => isset($context['ui_mode']) && is_string($context['ui_mode']) && $context['ui_mode'] !== ''
                ? $context['ui_mode']
                : null,
            'attachments' => isset($context['attachments']) && is_array($context['attachments']) ? $context['attachments'] : [],
        ], static fn ($value) => $value !== null && $value !== []);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|stdClass
     */
    private function encodeContextPayload(array $context): array|stdClass
    {
        if ($context === []) {
            return new stdClass();
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function shouldAllowGeneralAnswers(string $message, array $context): bool
    {
        if (isset($context['ui_mode']) && $context['ui_mode'] === 'general') {
            return true;
        }

        $normalized = Str::lower(trim($message));

        if ($normalized === '') {
            return false;
        }

        if ($this->matchesGreeting($normalized)) {
            return true;
        }

        if ($this->containsAny($normalized, self::WORKSPACE_KEYWORDS)) {
            return false;
        }

        return true;
    }

    private function matchesGreeting(string $text): bool
    {
        foreach (self::GENERAL_GREETINGS as $greeting) {
            if ($text === $greeting || str_starts_with($text, $greeting.' ')) {
                return true;
            }
        }

        return false;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && Str::contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item) => $item !== null));
    }

    /**
     * @param array<string, mixed> $assistantPayload
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function attachDraftSnapshot(
        AiChatThread $thread,
        User $user,
        array $assistantPayload,
        ?string $latestPrompt,
        array $context
    ): array {
        $responseType = $assistantPayload['type'] ?? null;
        $draftPayload = $assistantPayload['draft'] ?? null;

        if ($responseType !== 'draft_action' || ! is_array($draftPayload)) {
            return $assistantPayload;
        }

        if (isset($draftPayload['draft_id'])) {
            return $assistantPayload;
        }

        $actionType = $draftPayload['action_type'] ?? null;

        if (! is_string($actionType) || trim($actionType) === '') {
            return $assistantPayload;
        }

        $citations = $this->normalizeList($assistantPayload['citations'] ?? []);

        try {
            $draftModel = AiActionDraft::query()->create([
                'company_id' => $thread->company_id,
                'user_id' => $user->id,
                'action_type' => $actionType,
                'input_json' => $this->snapshotChatDraftInput($thread, $user, $latestPrompt, $context),
                'output_json' => $draftPayload,
                'citations_json' => $citations,
                'status' => AiActionDraft::STATUS_DRAFTED,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $assistantPayload;
        }

        $assistantPayload['draft'] = array_merge($draftPayload, [
            'draft_id' => $draftModel->id,
            'status' => $draftModel->status,
        ]);

        return $assistantPayload;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function snapshotChatDraftInput(
        AiChatThread $thread,
        User $user,
        ?string $latestPrompt,
        array $context
    ): array {
        $inputs = $context['context'] ?? [];
        $inputs = is_array($inputs) ? $inputs : [];
        $entityContext = $inputs['entity_context'] ?? null;
        $entityContext = is_array($entityContext) ? $entityContext : null;

        return [
            'source' => 'ai_chat',
            'thread_id' => $thread->id,
            'thread_title' => $thread->title,
            'query' => $latestPrompt ?? $this->latestUserPrompt($thread) ?? '',
            'inputs' => $inputs,
            'user_context' => $this->chatUserContext($user),
            'filters' => [],
            'top_k' => null,
            'entity_context' => $entityContext,
            'ui_mode' => $context['ui_mode'] ?? null,
            'attachments' => $context['attachments'] ?? [],
        ];
    }

    private function chatUserContext(User $user): array
    {
        $user->loadMissing('company:id,name');

        return array_filter([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_name' => $user->name,
            'job_title' => $user->job_title,
            'company_id' => $user->company_id,
            'company_name' => $user->company?->name,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function latestUserPrompt(AiChatThread $thread): ?string
    {
        return AiChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('role', AiChatMessage::ROLE_USER)
            ->orderByDesc('id')
            ->value('content_text');
    }

    /**
     * @param array<string, mixed>|null $assistantPayload
     * @param string|null $errorMessage
     */
    private function recordChatEvent(
        AiChatThread $thread,
        User $user,
        string $message,
        array $context,
        ?int $latency,
        ?array $assistantPayload,
        ?string $errorMessage
    ): void {
        $requestPayload = [
            'thread_id' => $thread->id,
            'message_preview' => $this->formatMessagePreview($message),
            'context_meta' => $this->sanitizeContextForLogging($context),
        ];

        $status = $errorMessage === null
            ? AiEvent::STATUS_SUCCESS
            : AiEvent::STATUS_ERROR;

        $this->recorder->record(
            companyId: $thread->company_id,
            userId: $user->id,
            feature: 'ai_chat_message_send',
            requestPayload: $requestPayload,
            responsePayload: $assistantPayload,
            latencyMs: $latency,
            status: $status,
            errorMessage: $errorMessage,
            entityType: 'ai_chat_thread',
            entityId: $thread->id,
        );
    }

    /**
     * @param array<string, mixed>|null $assistantPayload
     * @param string|null $errorMessage
     */
    private function recordToolEvent(
        AiChatThread $thread,
        User $user,
        array $toolCalls,
        array $context,
        ?int $latency,
        ?array $assistantPayload,
        ?string $errorMessage
    ): void {
        $requestPayload = [
            'thread_id' => $thread->id,
            'tool_calls' => $this->sanitizeToolCalls($toolCalls),
            'context_meta' => $this->sanitizeContextForLogging($context),
        ];

        $status = $errorMessage === null
            ? AiEvent::STATUS_SUCCESS
            : AiEvent::STATUS_ERROR;

        $this->recorder->record(
            companyId: $thread->company_id,
            userId: $user->id,
            feature: 'ai_chat_tool_resolve',
            requestPayload: $requestPayload,
            responsePayload: $assistantPayload,
            latencyMs: $latency,
            status: $status,
            errorMessage: $errorMessage,
            entityType: 'ai_chat_thread',
            entityId: $thread->id,
        );
    }
}

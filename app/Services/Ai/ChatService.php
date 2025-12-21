<?php

namespace App\Services\Ai;

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

        $toolResults = $this->toolResolver->resolveBatch($thread->company_id, $toolCalls);

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

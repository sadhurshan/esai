<?php

namespace App\Services\Ai;

use App\Enums\AiChatToolCall;
use App\Exceptions\AiChatException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\AiActionDraft;
use App\Models\AiChatMemory;
use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\User;
use Carbon\Carbon;
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
    private const INTENT_TOOL_ACTION_MAP = [
        'build_rfq_draft' => 'rfq_draft',
        'build_supplier_message' => 'supplier_message',
        'build_supplier_onboard_draft' => 'supplier_onboard_draft',
        'compare_quotes' => 'compare_quotes',
        'draft_purchase_order' => 'po_draft',
        'build_award_quote' => 'award_quote',
        'build_invoice_draft' => 'invoice_draft',
        'build_invoice_dispute_draft' => 'invoice_dispute_draft',
        'build_item_draft' => 'item_draft',
        'resolve_invoice_mismatch' => 'invoice_mismatch_resolution',
    ];
    private const INTENT_HELP_TOOLS = ['get_help'];

    private const ENTITY_PICKER_CONFIG = [
        AiChatToolCall::SearchInvoices->value => [
            'target_tool' => AiChatToolCall::GetInvoice->value,
            'entity_type' => 'invoice',
            'formatter' => 'formatInvoicePickerCandidates',
        ],
        AiChatToolCall::SearchPos->value => [
            'target_tool' => AiChatToolCall::GetPo->value,
            'entity_type' => 'purchase order',
            'formatter' => 'formatPurchaseOrderPickerCandidates',
        ],
        AiChatToolCall::SearchRfqs->value => [
            'target_tool' => AiChatToolCall::GetRfq->value,
            'entity_type' => 'RFQ',
            'formatter' => 'formatRfqPickerCandidates',
        ],
        AiChatToolCall::SearchSuppliers->value => [
            'target_tool' => AiChatToolCall::GetSupplier->value,
            'entity_type' => 'supplier',
            'formatter' => 'formatSupplierPickerCandidates',
        ],
        AiChatToolCall::SearchItems->value => [
            'target_tool' => AiChatToolCall::GetItem->value,
            'entity_type' => 'item',
            'formatter' => 'formatItemPickerCandidates',
        ],
    ];

    private const UNSAFE_DRAFT_ACTION_TYPES = [
        AiActionDraft::TYPE_APPROVE_INVOICE,
        AiActionDraft::TYPE_PAYMENT_DRAFT,
        'payment_process',
        'award_quote',
    ];

    private int $historyLimit;
    private int $streamTokenTtl;
    private bool $streamingEnabled;
    private bool $memoryEnabled;
    private int $summaryMaxChars;
    private int $threadSummaryLimit;
    private int $memoryTurnLimit;
    private int $memoryMessageMaxChars;
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
        $this->memoryTurnLimit = max(1, (int) config('ai_chat.memory.turn_limit', 5));
        $this->memoryMessageMaxChars = max(60, (int) config('ai_chat.memory.message_max_chars', 360));
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

        try {
            $toolResults = $this->dispatchWorkspaceTools($thread, $user, $toolCalls, $structuredContext);
        } catch (AiChatException $exception) {
            return $this->respondWithGuidedResolutionFallback($thread, $user, $toolCalls, $structuredContext, $exception);
        }

        $toolMessage = $thread->appendMessage(AiChatMessage::ROLE_TOOL, [
            'user_id' => $user->id,
            'content_text' => 'Workspace tool results ready.',
            'content_json' => ['tool_results' => $toolResults],
            'tool_results_json' => $toolResults,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $entityPickerResponse = $this->maybeRespondWithEntityPicker(
            $thread,
            $user,
            $toolCalls,
            $toolResults,
            $structuredContext,
            $toolMessage,
        );

        if ($entityPickerResponse !== null) {
            return $entityPickerResponse;
        }

        $structuredContext = $this->withMemoryContext($thread, $structuredContext);

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

        $operationStartedAt = microtime(true);
        $providerStartedAt = microtime(true);

        try {
            $response = $this->client->chatContinue($payload);
        } catch (AiServiceUnavailableException $exception) {
            $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
            $telemetry = $this->buildLatencyBreakdownTelemetry(
                operation: 'ai_chat_tool_resolve',
                mode: 'sync',
                totalMs: $totalLatency,
                providerMs: null,
                appMs: null,
            );

            $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, null, null, $exception->getMessage(), $telemetry);

            throw $exception;
        }

        $latency = (int) round((microtime(true) - $providerStartedAt) * 1000);
        $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
        $telemetry = $this->buildLatencyBreakdownTelemetry(
            operation: 'ai_chat_tool_resolve',
            mode: 'sync',
            totalMs: $totalLatency,
            providerMs: $latency,
            appMs: max(0, $totalLatency - $latency),
        );

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, $latency, null, $response['message'] ?? 'Chat service error.', $telemetry);

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

        $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
        $telemetry = $this->buildLatencyBreakdownTelemetry(
            operation: 'ai_chat_tool_resolve',
            mode: 'sync',
            totalMs: $totalLatency,
            providerMs: $latency,
            appMs: max(0, $totalLatency - $latency),
        );

        $this->recordToolEvent($thread, $user, $toolCalls, $structuredContext, $latency, $assistantPayload, null, $telemetry);

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

        $messageContextPayload = $structuredContext;
        unset($messageContextPayload['clarification'], $messageContextPayload['entity_picker']);

        $userMessage = $thread->appendMessage(AiChatMessage::ROLE_USER, [
            'user_id' => $user->id,
            'content_text' => $message,
            'content_json' => $messageContextPayload === [] ? null : ['context' => $messageContextPayload],
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $pendingClarification = $this->pendingClarification($thread);
        $clarificationReply = $structuredContext['clarification'] ?? null;

        if ($clarificationReply !== null && $pendingClarification !== null) {
            $clarificationResponse = $this->handleClarificationReply(
                $thread,
                $user,
                $message,
                $structuredContext,
                $clarificationReply,
                $pendingClarification,
                $userMessage,
            );

            if ($clarificationResponse !== null) {
                return $clarificationResponse;
            }
        } elseif ($clarificationReply === null && $pendingClarification !== null) {
            $this->clearPendingClarification($thread);
        }

        unset($structuredContext['clarification']);

        $pendingEntityPicker = $this->pendingEntityPicker($thread);
        $entityPickerReply = $structuredContext['entity_picker'] ?? null;

        if ($entityPickerReply !== null && $pendingEntityPicker !== null) {
            $entityPickerResponse = $this->handleEntityPickerReply(
                $thread,
                $user,
                $message,
                $structuredContext,
                $entityPickerReply,
                $pendingEntityPicker,
                $userMessage,
            );

            if ($entityPickerResponse !== null) {
                return $entityPickerResponse;
            }
        } elseif ($entityPickerReply === null && $pendingEntityPicker !== null) {
            $this->clearPendingEntityPicker($thread);
        }

        unset($structuredContext['entity_picker']);

        $conversationHistory = $this->conversationHistory($thread);
        $plannerResult = $this->runIntentPlanner($thread, $user, $message, $conversationHistory);
        if ($plannerResult !== null) {
            if ($this->hasPlannerSteps($plannerResult)) {
                $planResponse = $this->handlePlannerPlan(
                    $thread,
                    $user,
                    $message,
                    $structuredContext,
                    $plannerResult,
                    $userMessage,
                );

                if ($planResponse !== null) {
                    return $planResponse;
                }
            }

            $plannerTool = $this->stringValue($plannerResult['tool'] ?? null);

            if ($plannerTool === 'clarification') {
                $clarificationResponse = $this->handlePlannerClarification(
                    $thread,
                    $user,
                    $message,
                    $structuredContext,
                    $plannerResult,
                    $userMessage,
                );

                if ($clarificationResponse !== null) {
                    return $clarificationResponse;
                }
            } elseif ($plannerTool !== null && $plannerTool !== 'plan') {
                $plannerResponse = $this->handlePlannedTool(
                    $thread,
                    $user,
                    $message,
                    $structuredContext,
                    $plannerResult,
                    $userMessage,
                );

                if ($plannerResponse !== null) {
                    return $plannerResponse;
                }
            }
        }

        $structuredContext = $this->withMemoryContext($thread, $structuredContext);

        $payload = [
            'thread_id' => (string) $thread->id,
            'company_id' => $thread->company_id,
            'user_id' => $user->id,
            'user_id_hash' => $this->hashUser($user),
            'messages' => $conversationHistory,
            'context' => $this->encodeContextPayload($structuredContext),
            'thread_summary' => $this->normalizeThreadSummary($thread->thread_summary),
            'allow_general' => $allowGeneral,
        ];

        $operationStartedAt = microtime(true);
        $providerStartedAt = microtime(true);

        try {
            $response = $this->client->chatRespond($payload);
        } catch (AiServiceUnavailableException $exception) {
            $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
            $telemetry = $this->buildLatencyBreakdownTelemetry(
                operation: 'ai_chat_message_send',
                mode: 'sync',
                totalMs: $totalLatency,
                providerMs: null,
                appMs: null,
            );

            $this->recordChatEvent($thread, $user, $message, $structuredContext, null, null, $exception->getMessage(), $telemetry);

            throw $exception;
        }

        $latency = (int) round((microtime(true) - $providerStartedAt) * 1000);
        $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
        $telemetry = $this->buildLatencyBreakdownTelemetry(
            operation: 'ai_chat_message_send',
            mode: 'sync',
            totalMs: $totalLatency,
            providerMs: $latency,
            appMs: max(0, $totalLatency - $latency),
        );

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            $this->recordChatEvent($thread, $user, $message, $structuredContext, $latency, null, $response['message'] ?? 'Chat service error.', $telemetry);

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

        $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
        $telemetry = $this->buildLatencyBreakdownTelemetry(
            operation: 'ai_chat_message_send',
            mode: 'sync',
            totalMs: $totalLatency,
            providerMs: $latency,
            appMs: max(0, $totalLatency - $latency),
        );

        $this->recordChatEvent($thread, $user, $message, $structuredContext, $latency, $assistantPayload, null, $telemetry);

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

        $structuredContext = $this->withMemoryContext($thread, $structuredContext);

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
        $operationStartedAt = microtime(true);
        $providerStartedAt = microtime(true);
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
            $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
            $telemetry = $this->buildLatencyBreakdownTelemetry(
                operation: 'ai_chat_message_send',
                mode: 'stream',
                totalMs: $totalLatency,
                providerMs: null,
                appMs: null,
            );

            $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, null, null, $exception->getMessage(), $telemetry);
            report($exception);

            return;
        }

        if (! is_array($assistantPayload)) {
            $emitter($this->buildSseFrame('error', ['message' => 'Streaming finished without a response.']));
            $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
            $telemetry = $this->buildLatencyBreakdownTelemetry(
                operation: 'ai_chat_message_send',
                mode: 'stream',
                totalMs: $totalLatency,
                providerMs: null,
                appMs: null,
            );

            $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, null, null, 'Streaming response incomplete.', $telemetry);

            return;
        }

        if (! isset($assistantPayload['assistant_message_markdown']) || ! is_string($assistantPayload['assistant_message_markdown'])) {
            $assistantPayload['assistant_message_markdown'] = $accumulatedMarkdown;
        }

        $assistantPayload = $this->attachDraftSnapshot($thread, $user, $assistantPayload, $latestPrompt, $structuredContext);

        $latency = (int) round((microtime(true) - $providerStartedAt) * 1000);
        $totalLatency = (int) round((microtime(true) - $operationStartedAt) * 1000);
        $telemetry = $this->buildLatencyBreakdownTelemetry(
            operation: 'ai_chat_message_send',
            mode: 'stream',
            totalMs: $totalLatency,
            providerMs: $latency,
            appMs: max(0, $totalLatency - $latency),
        );

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => $this->normalizeList($assistantPayload['tool_calls'] ?? []),
            'tool_results_json' => $this->normalizeList($assistantPayload['tool_results'] ?? []),
            'latency_ms' => $latency,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->recordChatEvent($thread, $user, $latestPrompt ?? '', $structuredContext, $latency, $assistantPayload, null, $telemetry);

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

    /**
     * @return array<string, mixed>
     */
    public function getMemory(AiChatThread|int $thread): array
    {
        if (! $this->memoryEnabled || $this->memoryTurnLimit < 1) {
            return [];
        }

        $threadModel = $thread instanceof AiChatThread
            ? $thread
            : AiChatThread::query()->whereKey($thread)->first();

        if (! $threadModel instanceof AiChatThread) {
            return [];
        }

        $latestMessageId = $this->latestConversationMessageId($threadModel->id);

        $existingMemory = AiChatMemory::query()
            ->where('thread_id', $threadModel->id)
            ->first();

        if (
            $existingMemory !== null
            && $latestMessageId !== null
            && (int) $existingMemory->last_message_id === (int) $latestMessageId
            && is_array($existingMemory->memory_json)
        ) {
            return $existingMemory->memory_json;
        }

        $turnLimit = max(1, $this->memoryTurnLimit * 2);

        $messages = AiChatMessage::query()
            ->where('thread_id', $threadModel->id)
            ->whereIn('role', [AiChatMessage::ROLE_USER, AiChatMessage::ROLE_ASSISTANT])
            ->orderByDesc('id')
            ->limit($turnLimit)
            ->get()
            ->sortBy('id')
            ->values();

        if ($messages->isEmpty()) {
            return [];
        }

        $turns = $messages->map(function (AiChatMessage $message): array {
            return [
                'role' => $message->role,
                'content' => $this->truncateMemoryContent($this->resolveMessageContent($message)),
                'message_id' => $message->id,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ];
        })->all();

        $payload = [
            'turns' => $turns,
            'turn_count' => count($turns),
            'captured_at' => now()->toIso8601String(),
        ];

        if ($latestMessageId !== null) {
            $payload['last_message_id'] = $latestMessageId;
        }

        AiChatMemory::query()->updateOrCreate(
            ['thread_id' => $threadModel->id],
            [
                'company_id' => $threadModel->company_id,
                'last_message_id' => $latestMessageId,
                'memory_json' => $payload,
            ]
        );

        return $payload;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function withMemoryContext(AiChatThread $thread, array $context): array
    {
        if (! $this->memoryEnabled) {
            return $context;
        }

        $memory = $this->getMemory($thread);

        if ($memory === [] && isset($context['memory'])) {
            unset($context['memory']);
        }

        if ($memory === []) {
            return $context;
        }

        $context['memory'] = $memory;

        return $context;
    }

    private function latestConversationMessageId(int $threadId): ?int
    {
        return AiChatMessage::query()
            ->where('thread_id', $threadId)
            ->whereIn('role', [AiChatMessage::ROLE_USER, AiChatMessage::ROLE_ASSISTANT])
            ->orderByDesc('id')
            ->value('id');
    }

    private function truncateMemoryContent(string $content): string
    {
        $normalized = trim($content);

        if ($normalized === '') {
            return '[no content]';
        }

        return Str::limit($normalized, $this->memoryMessageMaxChars);
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
        $locale = isset($context['locale']) ? $this->sanitizeLocale($context['locale']) : null;

        return array_filter([
            'has_structured_context' => $hasContext ? true : null,
            'ui_mode' => $uiMode !== '' ? $uiMode : null,
            'attachment_count' => $attachmentCount > 0 ? $attachmentCount : null,
            'locale' => $locale,
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

            if ($toolName === AiChatToolCall::CreateDisputeDraft->value) {
                $resultsByIndex[$index] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'result' => $this->resolveDisputeDraftTool($thread, $user, $arguments, $context),
                ];

                continue;
            }

            if ($toolName === AiChatToolCall::ResolveInvoiceMismatch->value) {
                $resultsByIndex[$index] = [
                    'tool_name' => $toolName,
                    'call_id' => $callId,
                    'result' => $this->resolveInvoiceMismatchTool($thread, $user, $arguments, $context),
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

    private function respondWithGuidedResolutionFallback(
        AiChatThread $thread,
        User $user,
        array $toolCalls,
        array $context,
        AiChatException $exception
    ): array {
        $sanitizedToolCalls = $this->sanitizeToolCalls($toolCalls);
        $reason = $exception->getMessage();
        $helpResult = $this->buildHelpToolResult($thread, $context, $sanitizedToolCalls, $reason);
        $toolResults = [$helpResult];

        $toolMessage = $thread->appendMessage(AiChatMessage::ROLE_TOOL, [
            'user_id' => $user->id,
            'content_text' => 'Workspace guide shared instead of running a tool.',
            'content_json' => [
                'tool_results' => $toolResults,
                'fallback_reason' => $reason,
                'tool_calls' => $sanitizedToolCalls,
            ],
            'tool_results_json' => $toolResults,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $assistantPayload = $this->buildGuidedResolutionPayload($helpResult, $sanitizedToolCalls, $reason);

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => [],
            'tool_results_json' => $toolResults,
            'latency_ms' => null,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->recordToolEvent($thread, $user, $toolCalls, $context, null, $assistantPayload, $reason);

        return [
            'tool_message' => $toolMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    private function maybeRespondWithEntityPicker(
        AiChatThread $thread,
        User $user,
        array $toolCalls,
        array $toolResults,
        array $context,
        AiChatMessage $toolMessage
    ): ?array {
        if (count($toolResults) !== 1) {
            return null;
        }

        $result = $toolResults[0];
        $toolName = $this->stringValue($result['tool_name'] ?? null);

        if ($toolName === null || ! isset(self::ENTITY_PICKER_CONFIG[$toolName])) {
            return null;
        }

        $config = self::ENTITY_PICKER_CONFIG[$toolName];
        $payload = is_array($result['result'] ?? null) ? $result['result'] : [];
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];

        if (count($items) < 2) {
            return null;
        }

        $meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
        $query = $this->stringValue($meta['query'] ?? null) ?? '';

        if ($query === '' || strlen($query) < 2) {
            return null;
        }

        $totalCount = isset($meta['total_count']) ? (int) $meta['total_count'] : count($items);
        if ($totalCount > 20) {
            return null;
        }

        $formatter = $config['formatter'] ?? null;
        $candidates = [];

        if (is_string($formatter) && method_exists($this, $formatter)) {
            /** @var array<int, array<string, mixed>> $candidates */
            $candidates = $this->{$formatter}($items);
        }

        $candidates = array_values(array_filter($candidates, static function ($candidate): bool {
            if (! is_array($candidate)) {
                return false;
            }

            $candidateId = $candidate['candidate_id'] ?? null;
            $args = $candidate['args'] ?? null;

            return is_scalar($candidateId) && $candidateId !== '' && is_array($args);
        }));

        if (count($candidates) < 2) {
            return null;
        }

        $candidates = array_slice($candidates, 0, 5);

        $entityType = $this->stringValue($config['entity_type'] ?? null) ?? 'record';
        $targetTool = $this->stringValue($config['target_tool'] ?? null);

        if ($targetTool === null || $targetTool === '') {
            return null;
        }

        $promptId = (string) Str::uuid();
        $publicCandidates = array_map(static function (array $candidate): array {
            return [
                'candidate_id' => (string) $candidate['candidate_id'],
                'label' => $candidate['label'] ?? (string) $candidate['candidate_id'],
                'description' => $candidate['description'] ?? null,
                'status' => $candidate['status'] ?? null,
                'meta' => array_values(array_filter($candidate['meta'] ?? [])),
            ];
        }, $candidates);

        if (count($publicCandidates) < 2) {
            return null;
        }

        $assistantPayload = [
            'type' => 'entity_picker',
            'assistant_message_markdown' => $this->formatEntityPickerMarkdown($entityType, $query, count($publicCandidates)),
            'citations' => [],
            'suggested_quick_replies' => [],
            'entity_picker' => [
                'id' => $promptId,
                'title' => sprintf('Select %s', strtolower(Str::plural($entityType))),
                'description' => 'Pick the record that matches so Copilot can continue.',
                'query' => $query,
                'entity_type' => $entityType,
                'search_tool' => $toolName,
                'target_tool' => $targetTool,
                'candidates' => $publicCandidates,
            ],
            'tool_results' => $toolResults,
            'warnings' => [],
            'needs_human_review' => false,
            'confidence' => 0.0,
        ];

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => (string) ($assistantPayload['assistant_message_markdown'] ?? ''),
            'content_json' => $assistantPayload,
            'citations_json' => [],
            'tool_calls_json' => [],
            'tool_results_json' => $toolResults,
            'latency_ms' => null,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->clearPendingEntityPicker($thread);

        $this->storePendingEntityPicker($thread, [
            'id' => $promptId,
            'search_tool' => $toolName,
            'target_tool' => $targetTool,
            'entity_type' => $entityType,
            'query' => $query,
            'created_at' => now()->toIso8601String(),
            'candidates' => array_map(static function (array $candidate): array {
                return [
                    'candidate_id' => (string) $candidate['candidate_id'],
                    'args' => $candidate['args'],
                ];
            }, $candidates),
        ]);

        $this->recordToolEvent($thread, $user, $toolCalls, $context, null, $assistantPayload, null);

        return [
            'tool_message' => $toolMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    private function formatEntityPickerMarkdown(string $entityType, string $query, int $count): string
    {
        $pluralType = $count === 1 ? $entityType : Str::plural($entityType);
        $intro = sprintf('I found %d %s matching "%s".', $count, strtolower($pluralType), $query);

        return $intro . ' Select the correct record so I can continue.';
    }

    private function formatInvoicePickerCandidates(array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $invoiceId = $this->idValue($item['invoice_id'] ?? $item['id'] ?? null);

            if ($invoiceId === null) {
                continue;
            }

            $invoiceNumber = $this->stringValue($item['invoice_number'] ?? null) ?? sprintf('Invoice %d', $invoiceId);
            $status = $this->stringValue($item['status'] ?? null);

            $supplierName = null;
            if (isset($item['supplier']) && is_array($item['supplier'])) {
                $supplierName = $this->stringValue($item['supplier']['name'] ?? null);
            } elseif (isset($item['supplier_name'])) {
                $supplierName = $this->stringValue($item['supplier_name']);
            }

            $purchaseOrder = null;
            if (isset($item['purchase_order']) && is_array($item['purchase_order'])) {
                $purchaseOrder = $this->stringValue($item['purchase_order']['po_number'] ?? null);
            } elseif (isset($item['po_number'])) {
                $purchaseOrder = $this->stringValue($item['po_number']);
            }

            $total = $this->stringValue($item['total'] ?? null);
            $descriptionParts = array_values(array_filter([$supplierName, $total], static fn ($value) => $value !== null && $value !== ''));
            $description = $descriptionParts === [] ? null : implode(' • ', $descriptionParts);

            $meta = array_values(array_filter([
                $this->formatPickerDate($item['due_date'] ?? null, 'Due'),
                $purchaseOrder !== null ? sprintf('PO %s', $purchaseOrder) : null,
                isset($item['exceptions']) && is_array($item['exceptions']) && $item['exceptions'] !== []
                    ? sprintf('%d exception%s', count($item['exceptions']), count($item['exceptions']) === 1 ? '' : 's')
                    : null,
            ], static fn ($value) => $value !== null && $value !== ''));

            $candidates[] = [
                'candidate_id' => (string) $invoiceId,
                'label' => $invoiceNumber,
                'description' => $description,
                'status' => $status,
                'meta' => $meta,
                'args' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                ],
            ];
        }

        return $candidates;
    }

    private function formatPurchaseOrderPickerCandidates(array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $poId = $this->idValue($item['po_id'] ?? $item['id'] ?? null);
            if ($poId === null) {
                continue;
            }

            $poNumber = $this->stringValue($item['po_number'] ?? null) ?? sprintf('PO-%d', $poId);
            $status = $this->stringValue($item['status'] ?? null);
            $supplierName = null;
            if (isset($item['supplier']) && is_array($item['supplier'])) {
                $supplierName = $this->stringValue($item['supplier']['name'] ?? null);
            }

            $total = $this->stringValue($item['total'] ?? null);
            $descriptionParts = array_values(array_filter([$supplierName, $total], static fn ($value) => $value !== null && $value !== ''));
            $description = $descriptionParts === [] ? null : implode(' • ', $descriptionParts);

            $meta = array_values(array_filter([
                $this->formatPickerDate($item['ordered_at'] ?? null, 'Ordered'),
                $this->formatPickerDate($item['expected_at'] ?? null, 'Expected'),
            ], static fn ($value) => $value !== null && $value !== ''));

            $candidates[] = [
                'candidate_id' => (string) $poId,
                'label' => $poNumber,
                'description' => $description,
                'status' => $status,
                'meta' => $meta,
                'args' => [
                    'po_id' => $poId,
                    'po_number' => $poNumber,
                ],
            ];
        }

        return $candidates;
    }

    private function formatRfqPickerCandidates(array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rfqId = $this->idValue($item['rfq_id'] ?? $item['id'] ?? null);
            if ($rfqId === null) {
                continue;
            }

            $number = $this->stringValue($item['number'] ?? null) ?? sprintf('RFQ-%d', $rfqId);
            $title = $this->stringValue($item['title'] ?? null);
            $status = $this->stringValue($item['status'] ?? null);

            $meta = array_values(array_filter([
                $this->formatPickerDate($item['due_at'] ?? null, 'Due'),
                $this->formatPickerDate($item['close_at'] ?? null, 'Close'),
            ], static fn ($value) => $value !== null && $value !== ''));

            $candidates[] = [
                'candidate_id' => (string) $rfqId,
                'label' => $number,
                'description' => $title,
                'status' => $status,
                'meta' => $meta,
                'args' => [
                    'rfq_id' => $rfqId,
                ],
            ];
        }

        return $candidates;
    }

    private function formatSupplierPickerCandidates(array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $supplierId = $this->idValue($item['supplier_id'] ?? $item['id'] ?? null);
            if ($supplierId === null) {
                continue;
            }

            $name = $this->stringValue($item['name'] ?? null) ?? sprintf('Supplier %d', $supplierId);
            $status = $this->stringValue($item['status'] ?? null);
            $location = $this->stringValue($item['location'] ?? null);
            $leadTime = isset($item['lead_time_days']) && is_numeric($item['lead_time_days'])
                ? sprintf('Lead time %d days', (int) $item['lead_time_days'])
                : null;
            $rating = isset($item['rating']) && is_numeric($item['rating'])
                ? sprintf('Rating %.1f', (float) $item['rating'])
                : null;

            $meta = array_values(array_filter([
                $location,
                $leadTime,
                $rating,
            ], static fn ($value) => $value !== null && $value !== ''));

            $capabilities = isset($item['capability_highlights']) && is_array($item['capability_highlights'])
                ? array_values(array_filter($item['capability_highlights'], static fn ($entry) => is_string($entry) && $entry !== ''))
                : [];

            $description = $capabilities === [] ? null : implode(', ', array_slice($capabilities, 0, 3));

            $candidates[] = [
                'candidate_id' => (string) $supplierId,
                'label' => $name,
                'description' => $description,
                'status' => $status,
                'meta' => $meta,
                'args' => [
                    'supplier_id' => $supplierId,
                ],
            ];
        }

        return $candidates;
    }

    private function formatItemPickerCandidates(array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemId = $this->idValue($item['part_id'] ?? $item['item_id'] ?? $item['id'] ?? null);
            if ($itemId === null) {
                continue;
            }

            $partNumber = $this->stringValue($item['part_number'] ?? null) ?? sprintf('Item-%d', $itemId);
            $name = $this->stringValue($item['name'] ?? null);
            $status = $this->stringValue($item['status'] ?? null);
            $category = $this->stringValue($item['category'] ?? null);
            $uom = $this->stringValue($item['uom'] ?? null);

            $description = array_values(array_filter([$name, $category], static fn ($value) => $value !== null && $value !== ''));
            $meta = array_values(array_filter([
                $uom !== null ? sprintf('UOM %s', $uom) : null,
                $this->formatPickerDate($item['updated_at'] ?? null, 'Updated'),
            ], static fn ($value) => $value !== null && $value !== ''));

            $candidates[] = [
                'candidate_id' => (string) $itemId,
                'label' => $partNumber,
                'description' => $description === [] ? null : implode(' • ', $description),
                'status' => $status,
                'meta' => $meta,
                'args' => [
                    'item_id' => $itemId,
                    'part_number' => $partNumber,
                ],
            ];
        }

        return $candidates;
    }

    private function formatPickerDate(mixed $value, string $prefix): ?string
    {
        $timestamp = $this->stringValue($value);

        if ($timestamp === null) {
            return null;
        }

        try {
            $date = Carbon::parse($timestamp);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $formatted = $date->isoFormat('MMM D, YYYY');

        return $prefix !== '' ? sprintf('%s %s', $prefix, $formatted) : $formatted;
    }

    private function buildHelpToolResult(
        AiChatThread $thread,
        array $context,
        array $toolCalls,
        string $reason
    ): array {
        $callId = Str::uuid()->toString();
        $topic = $this->inferHelpTopic($toolCalls);
        $locale = $this->sanitizeLocale($context['locale'] ?? null);
        $arguments = [
            'topic' => $topic,
            'action' => $topic,
            'context' => $this->buildHelpContextBlocks($context, $toolCalls, $reason),
        ];

        if ($locale !== null) {
            $arguments['locale'] = $locale;
        }

        try {
            $results = $this->toolResolver->resolveBatch($thread->company_id, [[
                'tool_name' => AiChatToolCall::Help->value,
                'call_id' => $callId,
                'arguments' => $arguments,
            ]]);

            if (isset($results[0])) {
                return $results[0];
            }
        } catch (Throwable $fallbackException) {
            report($fallbackException);
        }

        return [
            'tool_name' => AiChatToolCall::Help->value,
            'call_id' => $callId,
            'result' => [
                'summary' => 'Workspace help guide not available right now.',
                'payload' => [
                    'title' => 'Workspace help unavailable',
                    'description' => 'Copilot could not retrieve the fallback guide. Review the workflow manually or contact an admin.',
                    'steps' => [],
                    'cta_label' => 'Contact support',
                    'cta_url' => null,
                ],
                'citations' => [],
            ],
        ];
    }

    private function buildGuidedResolutionPayload(array $helpResult, array $toolCalls, string $reason): array
    {
        $result = is_array($helpResult['result'] ?? null) ? $helpResult['result'] : [];
        $guidePayload = is_array($result['payload'] ?? null) ? $result['payload'] : [];

        $topic = $this->inferHelpTopic($toolCalls);
        $title = $this->stringValue($guidePayload['title'] ?? null)
            ?? ($topic !== '' ? sprintf('%s guide', Str::title($topic)) : 'Workspace guide');
        $description = $this->stringValue($guidePayload['description'] ?? ($result['summary'] ?? null))
            ?? 'Follow these steps inside Elements Supply.';
        $ctaLabel = $this->stringValue($guidePayload['cta_label'] ?? null) ?? 'Open help center';
        $ctaUrl = $this->stringValue($guidePayload['cta_url'] ?? null);
        $steps = $this->normalizeGuideSteps($guidePayload['steps'] ?? null);
        $availableLocales = $this->normalizeList($guidePayload['available_locales'] ?? []);
        $resolutionLocale = $this->sanitizeLocale($guidePayload['locale'] ?? null) ?? 'en';

        $markdown = $this->formatGuidedResolutionMarkdown($topic, $description, $steps, $ctaLabel, $ctaUrl, $reason);

        return [
            'type' => 'guided_resolution',
            'assistant_message_markdown' => $markdown,
            'guided_resolution' => [
                'title' => $title,
                'description' => $description,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
                'locale' => $resolutionLocale,
                'available_locales' => $availableLocales,
            ],
            'tool_results' => [$helpResult],
            'citations' => $this->normalizeList($result['citations'] ?? []),
            'warnings' => ['Manual steps required while automation is unavailable.'],
        ];
    }

    private function inferHelpTopic(array $toolCalls): string
    {
        foreach ($toolCalls as $call) {
            $name = is_array($call) ? ($call['tool_name'] ?? null) : null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $humanized = $this->humanizeToolName($name);

            if ($humanized !== '') {
                return $humanized;
            }
        }

        return 'workspace guide';
    }

    private function humanizeToolName(string $toolName): string
    {
        $normalized = str_replace(['workspace.', '.'], ['', ' '], trim($toolName));
        $normalized = str_replace(['_', '-'], ' ', $normalized);

        return trim(Str::headline($normalized));
    }

    private function buildHelpContextBlocks(array $context, array $toolCalls, string $reason): array
    {
        $blocks = $this->normalizeToolContextBlocks($context['context'] ?? []);

        if (count($blocks) < 5) {
            $blocks[] = array_filter([
                'type' => 'tool_fallback',
                'tool_calls' => array_values(array_filter(array_map(
                    static fn ($call) => is_array($call) ? ($call['tool_name'] ?? null) : null,
                    $toolCalls
                ))),
                'reason' => $reason,
            ], static fn ($value) => $value !== null && $value !== []);
        }

        $filtered = array_values(array_filter($blocks, static fn ($block) => is_array($block) && $block !== []));

        return array_slice($filtered, 0, 5);
    }

    private function normalizeGuideSteps(mixed $steps): array
    {
        if (! is_array($steps)) {
            return [];
        }

        $normalized = [];

        foreach ($steps as $step) {
            $value = $this->stringValue($step);
            if ($value === null) {
                continue;
            }

            $normalized[] = $value;

            if (count($normalized) >= 8) {
                break;
            }
        }

        return $normalized;
    }

    private function formatGuidedResolutionMarkdown(
        string $topic,
        string $description,
        array $steps,
        string $ctaLabel,
        ?string $ctaUrl,
        string $reason
    ): string {
        $headline = $topic !== ''
            ? sprintf('Copilot could not complete the requested workspace lookup for **%s**.', $topic)
            : 'Copilot could not complete the requested workspace lookup.';

        $segments = [$headline];

        $reason = trim($reason);
        if ($reason !== '') {
            $segments[] = sprintf('_Reason_: %s', $reason);
        }

        if ($description !== '') {
            $segments[] = $description;
        }

        if ($steps !== []) {
            $lines = [];
            foreach ($steps as $index => $step) {
                $lines[] = sprintf('%d. %s', $index + 1, $step);
            }
            $segments[] = implode("\n", $lines);
        }

        if ($ctaUrl) {
            $segments[] = sprintf('[%s](%s)', $ctaLabel, $ctaUrl);
        } elseif ($ctaLabel !== '') {
            $segments[] = $ctaLabel;
        }

        $segments[] = 'Follow the guide above, then ask Copilot to continue once complete.';

        return implode("\n\n", array_filter($segments, static fn ($segment) => $segment !== ''));
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
    private function resolveDisputeDraftTool(
        AiChatThread $thread,
        User $user,
        array $arguments,
        array $context
    ): array {
        $inputs = $this->extractDisputeDraftInputs($arguments, $context);

        $requestPayload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => $this->normalizeToolContextBlocks($arguments['context'] ?? $context['context'] ?? []),
            'inputs' => $inputs,
        ];

        try {
            $response = $this->client->buildInvoiceDisputeDraftTool($requestPayload);
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Invoice dispute drafting service is unavailable. Please try again later.', null, 0, $exception);
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to build invoice dispute draft.', $response['errors'] ?? null);
        }

        $data = $response['data'];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Invoice dispute draft generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->normalizeList($data['citations'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveInvoiceMismatchTool(
        AiChatThread $thread,
        User $user,
        array $arguments,
        array $context
    ): array {
        $inputs = $this->extractInvoiceMismatchInputs($arguments, $context);

        $requestPayload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => $this->normalizeToolContextBlocks($arguments['context'] ?? $context['context'] ?? []),
            'inputs' => $inputs,
        ];

        try {
            $response = $this->client->resolveInvoiceMismatchTool($requestPayload);
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Invoice mismatch resolution service is unavailable. Please try again later.', null, 0, $exception);
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to build invoice mismatch resolution.', $response['errors'] ?? null);
        }

        $data = $response['data'];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Invoice mismatch resolution generated.',
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
    private function extractDisputeDraftInputs(array $arguments, array $context): array
    {
        $contextPayload = isset($context['context']) && is_array($context['context']) ? $context['context'] : [];
        $invoiceBlock = isset($contextPayload['invoice']) && is_array($contextPayload['invoice']) ? $contextPayload['invoice'] : [];
        $contextDisputeRef = isset($contextPayload['dispute_reference']) && is_array($contextPayload['dispute_reference'])
            ? $contextPayload['dispute_reference']
            : [];

        $inputs = [];

        $invoiceId = $this->stringValue(
            $arguments['invoice_id']
            ?? $contextPayload['invoice_id']
            ?? ($invoiceBlock['invoice_id'] ?? $invoiceBlock['id'] ?? null)
            ?? ($contextDisputeRef['invoice']['id'] ?? null)
        );

        $invoiceNumber = $this->stringValue(
            $arguments['invoice_number']
            ?? $contextPayload['invoice_number']
            ?? ($invoiceBlock['invoice_number'] ?? $invoiceBlock['number'] ?? null)
            ?? ($contextDisputeRef['invoice']['number'] ?? null)
        );

        if ($invoiceId === null && $invoiceNumber === null) {
            throw new AiChatException('Invoice dispute draft tool requires invoice_id or invoice_number argument.');
        }

        if ($invoiceId !== null) {
            $inputs['invoice_id'] = $invoiceId;
        }

        if ($invoiceNumber !== null) {
            $inputs['invoice_number'] = $invoiceNumber;
        }

        $poBlockFromContext = isset($contextPayload['purchase_order']) && is_array($contextPayload['purchase_order'])
            ? $contextPayload['purchase_order']
            : (isset($invoiceBlock['purchase_order']) && is_array($invoiceBlock['purchase_order']) ? $invoiceBlock['purchase_order'] : []);
        $poReference = isset($contextDisputeRef['purchase_order']) && is_array($contextDisputeRef['purchase_order'])
            ? $contextDisputeRef['purchase_order']
            : [];

        $purchaseOrderId = $this->stringValue(
            $arguments['purchase_order_id']
            ?? $arguments['po_id']
            ?? $contextPayload['purchase_order_id']
            ?? ($poBlockFromContext['purchase_order_id'] ?? $poBlockFromContext['id'] ?? null)
            ?? ($poReference['id'] ?? null)
        );
        $purchaseOrderNumber = $this->stringValue(
            $arguments['purchase_order_number']
            ?? $arguments['po_number']
            ?? $contextPayload['purchase_order_number']
            ?? ($poBlockFromContext['po_number'] ?? $poBlockFromContext['number'] ?? null)
            ?? ($poReference['number'] ?? null)
        );

        if ($purchaseOrderId !== null) {
            $inputs['purchase_order_id'] = $purchaseOrderId;
        }

        if ($purchaseOrderNumber !== null) {
            $inputs['purchase_order_number'] = $purchaseOrderNumber;
        }

        $receiptBlock = isset($contextPayload['receipt']) && is_array($contextPayload['receipt'])
            ? $contextPayload['receipt']
            : [];
        if ($receiptBlock === [] && isset($contextPayload['receipts']) && is_array($contextPayload['receipts'])) {
            $firstReceipt = $contextPayload['receipts'][0] ?? null;
            $receiptBlock = is_array($firstReceipt) ? $firstReceipt : [];
        }
        $receiptReference = isset($contextDisputeRef['receipt']) && is_array($contextDisputeRef['receipt'])
            ? $contextDisputeRef['receipt']
            : [];

        $receiptId = $this->stringValue(
            $arguments['receipt_id']
            ?? $contextPayload['receipt_id']
            ?? ($receiptBlock['receipt_id'] ?? $receiptBlock['id'] ?? null)
            ?? ($receiptReference['id'] ?? null)
        );
        $receiptNumber = $this->stringValue(
            $arguments['receipt_number']
            ?? $contextPayload['receipt_number']
            ?? ($receiptBlock['number'] ?? null)
            ?? ($receiptReference['number'] ?? null)
        );

        if ($receiptId !== null) {
            $inputs['receipt_id'] = $receiptId;
        }

        if ($receiptNumber !== null) {
            $inputs['receipt_number'] = $receiptNumber;
        }

        if (isset($arguments['dispute_reference']) && is_array($arguments['dispute_reference'])) {
            $inputs['dispute_reference'] = $arguments['dispute_reference'];
        } elseif ($contextDisputeRef !== []) {
            $inputs['dispute_reference'] = $contextDisputeRef;
        }

        $matchResult = $arguments['match_result']
            ?? $contextPayload['match_result']
            ?? ($invoiceBlock['match_result'] ?? null);
        if (is_array($matchResult) && $matchResult !== []) {
            $inputs['match_result'] = $matchResult;
        }

        $mismatches = $arguments['mismatches']
            ?? ($matchResult['mismatches'] ?? null)
            ?? $contextPayload['mismatches']
            ?? null;
        $normalizedMismatches = $this->normalizeMismatchEntries($mismatches);
        if ($normalizedMismatches !== []) {
            $inputs['mismatches'] = $normalizedMismatches;
        }

        $issueSummary = $this->stringValue($arguments['issue_summary'] ?? $contextPayload['issue_summary'] ?? null);
        if ($issueSummary !== null) {
            $inputs['issue_summary'] = $issueSummary;
        }

        $issueCategory = $this->stringValue($arguments['issue_category'] ?? $contextPayload['issue_category'] ?? null);
        if ($issueCategory !== null) {
            $inputs['issue_category'] = $issueCategory;
        }

        $resolutionType = $this->stringValue($arguments['resolution_type'] ?? $contextPayload['resolution_type'] ?? null);
        if ($resolutionType !== null) {
            $inputs['resolution_type'] = $resolutionType;
        }

        $ownerRole = $this->stringValue($arguments['owner_role'] ?? $contextPayload['owner_role'] ?? null);
        if ($ownerRole !== null) {
            $inputs['owner_role'] = $ownerRole;
        }

        $requiresHoldValue = $arguments['requires_hold'] ?? $contextPayload['requires_hold'] ?? null;
        if (is_bool($requiresHoldValue)) {
            $inputs['requires_hold'] = $requiresHoldValue;
        }

        $dueInDaysValue = $arguments['due_in_days'] ?? $contextPayload['due_in_days'] ?? null;
        if (is_numeric($dueInDaysValue)) {
            $inputs['due_in_days'] = max(0, min(120, (int) $dueInDaysValue));
        }

        $reasonCodes = $this->normalizeStringList($arguments['reason_codes'] ?? $contextPayload['reason_codes'] ?? null, 10);
        if ($reasonCodes !== []) {
            $inputs['reason_codes'] = $reasonCodes;
        }

        $actions = $this->normalizeDisputeActions($arguments['actions'] ?? $contextPayload['actions'] ?? null);
        if ($actions !== []) {
            $inputs['actions'] = $actions;
        }

        $impacts = $this->normalizeDisputeImpacts($arguments['impacted_lines'] ?? $contextPayload['impacted_lines'] ?? null);
        if ($impacts !== []) {
            $inputs['impacted_lines'] = $impacts;
        }

        $nextSteps = $this->normalizeStringList($arguments['next_steps'] ?? $contextPayload['next_steps'] ?? null, 10);
        if ($nextSteps !== []) {
            $inputs['next_steps'] = $nextSteps;
        }

        $notes = $this->normalizeStringList($arguments['notes'] ?? $contextPayload['notes'] ?? null, 10);
        if ($notes !== []) {
            $inputs['notes'] = $notes;
        }

        return $inputs;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function extractInvoiceMismatchInputs(array $arguments, array $context): array
    {
        $contextPayload = isset($context['context']) && is_array($context['context']) ? $context['context'] : [];
        $invoiceBlock = isset($contextPayload['invoice']) && is_array($contextPayload['invoice']) ? $contextPayload['invoice'] : [];

        $invoiceId = $this->stringValue(
            $arguments['invoice_id']
            ?? $contextPayload['invoice_id']
            ?? $contextPayload['entity_id']
            ?? ($invoiceBlock['invoice_id'] ?? $invoiceBlock['id'] ?? null)
        );

        if ($invoiceId === null) {
            throw new AiChatException('Invoice mismatch resolution tool requires invoice_id argument.');
        }

        $inputs = ['invoice_id' => $invoiceId];

        $matchResult = $arguments['match_result'] ?? $contextPayload['match_result'] ?? null;
        if (is_array($matchResult)) {
            $inputs['match_result'] = $matchResult;
        }

        $mismatches = $arguments['mismatches']
            ?? (is_array($matchResult) ? ($matchResult['mismatches'] ?? null) : null)
            ?? $contextPayload['mismatches']
            ?? null;
        $normalizedMismatches = $this->normalizeMismatchEntries($mismatches);
        if ($normalizedMismatches !== []) {
            $inputs['mismatches'] = $normalizedMismatches;
        }

        $preferredResolution = $this->stringValue($arguments['preferred_resolution'] ?? $contextPayload['preferred_resolution'] ?? null);
        if ($preferredResolution !== null) {
            $inputs['preferred_resolution'] = $preferredResolution;
        }

        $summary = $this->stringValue($arguments['summary'] ?? $contextPayload['summary'] ?? null);
        if ($summary !== null) {
            $inputs['summary'] = $summary;
        }

        $reasonCodes = $this->normalizeStringList($arguments['reason_codes'] ?? $contextPayload['reason_codes'] ?? null, 6);
        if ($reasonCodes !== []) {
            $inputs['reason_codes'] = $reasonCodes;
        }

        $notes = $this->normalizeStringList($arguments['notes'] ?? $contextPayload['notes'] ?? null, 5);
        if ($notes !== []) {
            $inputs['notes'] = $notes;
        }

        return $inputs;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeMismatchEntries(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized = [];

            foreach (['type', 'line_reference', 'severity', 'detail'] as $key) {
                $stringValue = $this->stringValue($entry[$key] ?? null);
                if ($stringValue !== null) {
                    $normalized[$key] = $stringValue;
                }
            }

            if (array_key_exists('variance', $entry) && is_numeric($entry['variance'])) {
                $normalized['variance'] = (float) $entry['variance'];
            }

            foreach (['expected', 'actual'] as $numericKey) {
                if (array_key_exists($numericKey, $entry) && $entry[$numericKey] !== null) {
                    $normalized[$numericKey] = $entry[$numericKey];
                }
            }

            if ($normalized === []) {
                continue;
            }

            $entries[] = $normalized;

            if (count($entries) >= 25) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{type:string,description:string,owner_role:?string,due_in_days:?int,requires_hold:bool}>
     */
    private function normalizeDisputeActions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $actions = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = $this->stringValue($entry['type'] ?? null);
            $description = $this->stringValue($entry['description'] ?? null);

            if ($type === null || $description === null) {
                continue;
            }

            $action = [
                'type' => $type,
                'description' => $description,
                'requires_hold' => (bool) ($entry['requires_hold'] ?? false),
            ];

            $ownerRole = $this->stringValue($entry['owner_role'] ?? null);
            if ($ownerRole !== null) {
                $action['owner_role'] = $ownerRole;
            }

            if (isset($entry['due_in_days']) && is_numeric($entry['due_in_days'])) {
                $action['due_in_days'] = max(0, min(120, (int) $entry['due_in_days']));
            }

            $actions[] = $action;

            if (count($actions) >= 10) {
                break;
            }
        }

        return $actions;
    }

    /**
     * @return list<array{reference:string,issue:string,severity:?string,variance:?float,recommended_action:string}>
     */
    private function normalizeDisputeImpacts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $impacts = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $reference = $this->stringValue($entry['reference'] ?? $entry['line_reference'] ?? null);
            $issue = $this->stringValue($entry['issue'] ?? null);
            $recommendation = $this->stringValue($entry['recommended_action'] ?? null);

            if ($reference === null || $issue === null || $recommendation === null) {
                continue;
            }

            $impact = [
                'reference' => $reference,
                'issue' => $issue,
                'recommended_action' => $recommendation,
            ];

            $severity = $this->stringValue($entry['severity'] ?? null);
            if ($severity !== null) {
                $impact['severity'] = $severity;
            }

            if (isset($entry['variance']) && is_numeric($entry['variance'])) {
                $impact['variance'] = (float) $entry['variance'];
            }

            $impacts[] = $impact;

            if (count($impacts) >= 10) {
                break;
            }
        }

        return $impacts;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value, int $limit = 10): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $entry) {
            $stringValue = $this->stringValue($entry);
            if ($stringValue !== null) {
                $normalized[] = $stringValue;
            }

            if (count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
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

    private function idValue(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
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

    private function sanitizeLocale(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('_', '-', $value)));

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 10);
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
            'locale' => $this->sanitizeLocale($context['locale'] ?? null),
            'clarification' => $this->normalizeClarificationReply($context['clarification'] ?? null),
            'entity_picker' => $this->normalizeEntityPickerReply($context['entity_picker'] ?? null),
        ], static fn ($value) => $value !== null && $value !== []);

        return $normalized;
    }

    private function normalizeClarificationReply(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $identifier = $this->stringValue($value['id'] ?? null);

        if ($identifier === null) {
            return null;
        }

        return ['id' => $identifier];
    }

    private function normalizeEntityPickerReply(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $identifier = $this->stringValue($value['id'] ?? null);
        $candidate = $this->stringValue($value['candidate_id'] ?? $value['candidate'] ?? null);

        if ($identifier === null || $candidate === null) {
            return null;
        }

        return [
            'id' => $identifier,
            'candidate_id' => $candidate,
        ];
    }

    private function normalizeClarificationArgs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $argument) {
            if (! is_string($argument)) {
                continue;
            }

            $trimmed = trim($argument);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;

            if (count($normalized) >= 5) {
                break;
            }
        }

        return $normalized;
    }

    private function clarificationAnswerValue(string $answer): string
    {
        $normalized = trim($answer);

        if ($normalized === '') {
            return $answer;
        }

        $patterns = [
            '/^(?:let\'s\s+call\s+it)\s+/i',
            '/^(?:call\s+it)\s+/i',
            '/^(?:name\s+it)\s+/i',
            '/^(?:title\s+it)\s+/i',
            '/^(?:it\s+should\s+be)\s+/i',
            '/^(?:make\s+it)\s+/i',
        ];

        foreach ($patterns as $pattern) {
            $count = 0;
            $stripped = preg_replace($pattern, '', $normalized, 1, $count);
            if (is_string($stripped) && $count > 0) {
                $normalized = $stripped;
                break;
            }
        }

        $normalized = trim($normalized);

        return $normalized === '' ? $answer : $normalized;
    }

    private function pendingClarification(AiChatThread $thread): ?array
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata)) {
            return null;
        }

        $clarification = $metadata['pending_clarification'] ?? null;

        if (! is_array($clarification)) {
            return null;
        }

        $identifier = $this->stringValue($clarification['id'] ?? null);

        if ($identifier === null) {
            return null;
        }

        $clarification['id'] = $identifier;

        return $clarification;
    }

    private function storePendingClarification(AiChatThread $thread, array $clarification): void
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['pending_clarification'] = $clarification;

        $thread->forceFill(['metadata_json' => $metadata])->save();
    }

    private function clearPendingClarification(AiChatThread $thread): void
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata) || ! array_key_exists('pending_clarification', $metadata)) {
            return;
        }

        unset($metadata['pending_clarification']);

        $thread->forceFill(['metadata_json' => $metadata])->save();
    }

    private function pendingEntityPicker(AiChatThread $thread): ?array
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata)) {
            return null;
        }

        $picker = $metadata['pending_entity_picker'] ?? null;

        if (! is_array($picker)) {
            return null;
        }

        $identifier = $this->stringValue($picker['id'] ?? null);
        $targetTool = $this->stringValue($picker['target_tool'] ?? null);
        $candidates = isset($picker['candidates']) && is_array($picker['candidates']) ? $picker['candidates'] : [];

        if ($identifier === null || $targetTool === null || $candidates === []) {
            return null;
        }

        $normalizedCandidates = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidateId = $this->stringValue($candidate['candidate_id'] ?? null);
            if ($candidateId === null) {
                continue;
            }

            $arguments = isset($candidate['args']) && is_array($candidate['args']) ? $candidate['args'] : [];
            $normalizedCandidates[] = [
                'candidate_id' => $candidateId,
                'args' => $arguments,
            ];
        }

        if ($normalizedCandidates === []) {
            return null;
        }

        $picker['id'] = $identifier;
        $picker['target_tool'] = $targetTool;
        $picker['candidates'] = $normalizedCandidates;

        return $picker;
    }

    private function storePendingEntityPicker(AiChatThread $thread, array $picker): void
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['pending_entity_picker'] = $picker;

        $thread->forceFill(['metadata_json' => $metadata])->save();
    }

    private function clearPendingEntityPicker(AiChatThread $thread): void
    {
        $metadata = $thread->metadata_json;

        if (! is_array($metadata) || ! array_key_exists('pending_entity_picker', $metadata)) {
            return;
        }

        unset($metadata['pending_entity_picker']);

        $thread->forceFill(['metadata_json' => $metadata])->save();
    }

    private function matchPendingEntityCandidate(mixed $candidates, string $candidateId): ?array
    {
        if (! is_array($candidates)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $identifier = $this->stringValue($candidate['candidate_id'] ?? null);

            if ($identifier === null || $identifier !== $candidateId) {
                continue;
            }

            $arguments = isset($candidate['args']) && is_array($candidate['args']) ? $candidate['args'] : [];

            return [
                'candidate_id' => $identifier,
                'args' => $arguments,
            ];
        }

        return null;
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

        $requiresUnsafeConfirmation = $this->isUnsafeDraftAction($actionType);

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

        if ($requiresUnsafeConfirmation) {
            $assistantPayload['type'] = 'unsafe_action_confirmation';
            $assistantPayload['unsafe_action'] = $this->buildUnsafeActionPrompt($actionType, $draftPayload);
        }

        return $assistantPayload;
    }

    private function isUnsafeDraftAction(string $actionType): bool
    {
        return in_array($actionType, self::UNSAFE_DRAFT_ACTION_TYPES, true);
    }

    private function buildUnsafeActionPrompt(string $actionType, array $draftPayload): array
    {
        $payload = isset($draftPayload['payload']) && is_array($draftPayload['payload']) ? $draftPayload['payload'] : [];
        $actionLabel = $this->formatActionLabel($actionType);
        $entityLabel = $this->unsafeEntityLabel($actionType, $payload);
        $summary = $this->stringValue($draftPayload['summary'] ?? null)
            ?? sprintf('%s requires your confirmation before Copilot runs it.', $actionLabel);

        return [
            'id' => (string) Str::uuid(),
            'action_type' => $actionType,
            'action_label' => $actionLabel,
            'headline' => $this->unsafeActionHeadline($actionType, $entityLabel),
            'summary' => $summary,
            'description' => $this->unsafeActionDescription($actionType, $payload, $entityLabel),
            'impact' => $this->unsafeActionImpact($actionType, $payload),
            'entity' => $entityLabel,
            'acknowledgement' => $this->unsafeActionAcknowledgement($actionType, $entityLabel),
            'confirm_label' => $this->unsafeActionConfirmLabel($actionType),
            'risks' => $this->unsafeActionRisks($actionType),
        ];
    }

    private function unsafeEntityLabel(string $actionType, array $payload): ?string
    {
        if ($actionType === AiActionDraft::TYPE_APPROVE_INVOICE) {
            $invoice = $this->stringValue($payload['invoice_number'] ?? $payload['invoice_id'] ?? null);

            return $invoice !== null ? sprintf('Invoice %s', $invoice) : null;
        }

        if (in_array($actionType, [AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process'], true)) {
            $reference = $this->stringValue($payload['payment_reference'] ?? $payload['reference'] ?? null);

            if ($reference !== null) {
                return sprintf('Payment %s', $reference);
            }

            $invoice = $this->stringValue($payload['invoice_id'] ?? null);

            return $invoice !== null ? sprintf('Invoice %s', $invoice) : null;
        }

        if ($actionType === 'award_quote') {
            $rfqId = $this->stringValue($payload['rfq_id'] ?? null);

            return $rfqId !== null ? sprintf('RFQ %s', $rfqId) : null;
        }

        return null;
    }

    private function unsafeActionHeadline(string $actionType, ?string $entityLabel): string
    {
        return match ($actionType) {
            AiActionDraft::TYPE_APPROVE_INVOICE => $entityLabel
                ? sprintf('Confirm payment for %s', $entityLabel)
                : 'Confirm invoice payment',
            AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process' => $entityLabel
                ? sprintf('Confirm release for %s', $entityLabel)
                : 'Confirm payment release',
            'award_quote' => 'Confirm supplier award',
            default => sprintf('Confirm %s', Str::lower($this->formatActionLabel($actionType))),
        };
    }

    private function unsafeActionDescription(string $actionType, array $payload, ?string $entityLabel): string
    {
        if ($actionType === AiActionDraft::TYPE_APPROVE_INVOICE) {
            $reference = $this->stringValue($payload['payment_reference'] ?? $payload['reference'] ?? null);

            if ($entityLabel !== null && $reference !== null) {
                return sprintf('Copilot will mark %s as paid and log payment reference %s.', $entityLabel, $reference);
            }

            if ($entityLabel !== null) {
                return sprintf('Copilot will mark %s as paid in Accounts Payable.', $entityLabel);
            }

            return 'Copilot will mark the invoice as paid and close out the balance.';
        }

        if (in_array($actionType, [AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process'], true)) {
            $reference = $this->stringValue($payload['payment_reference'] ?? $payload['reference'] ?? null);
            $invoice = $this->stringValue($payload['invoice_id'] ?? null);

            if ($reference !== null && $invoice !== null) {
                return sprintf('Copilot will release payment %s against invoice %s.', $reference, $invoice);
            }

            if ($reference !== null) {
                return sprintf('Copilot will release payment %s.', $reference);
            }

            return 'Copilot will create a payment ready for disbursement.';
        }

        if ($actionType === 'award_quote') {
            $rfqId = $this->stringValue($payload['rfq_id'] ?? null);
            $supplier = $this->stringValue($payload['supplier_id'] ?? null);
            $quoteId = $this->stringValue($payload['selected_quote_id'] ?? null);

            if ($rfqId !== null && $supplier !== null && $quoteId !== null) {
                return sprintf('Copilot will award RFQ %s to supplier %s using quote %s.', $rfqId, $supplier, $quoteId);
            }

            return 'Copilot will finalize the supplier award and lock the chosen quote.';
        }

        return sprintf('Copilot will execute the %s action.', $this->formatActionLabel($actionType));
    }

    private function unsafeActionImpact(string $actionType, array $payload): ?string
    {
        if (in_array($actionType, [AiActionDraft::TYPE_APPROVE_INVOICE, AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process'], true)) {
            $amount = $this->formatCurrencyAmount(
                $payload['payment_amount'] ?? $payload['amount'] ?? null,
                $payload['payment_currency'] ?? $payload['currency'] ?? null,
            );

            if ($amount !== null) {
                return sprintf('%s will be recorded immediately.', $amount);
            }

            return 'Funds will be recorded immediately.';
        }

        if ($actionType === 'award_quote') {
            $deliveryDate = $this->stringValue($payload['delivery_date'] ?? null);

            if ($deliveryDate !== null) {
                return sprintf('Award locks the quote and sets delivery for %s.', $deliveryDate);
            }

            return 'Award locks pricing, delivery commitments, and supplier capacity.';
        }

        return null;
    }

    private function unsafeActionAcknowledgement(string $actionType, ?string $entityLabel): string
    {
        if ($actionType === AiActionDraft::TYPE_APPROVE_INVOICE) {
            return $entityLabel !== null
                ? sprintf('I understand approving this will mark %s as paid.', $entityLabel)
                : 'I understand this will mark the invoice as paid.';
        }

        if (in_array($actionType, [AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process'], true)) {
            return 'I understand this will release a payment request to finance.';
        }

        if ($actionType === 'award_quote') {
            return 'I understand this commits the RFQ award to the selected supplier.';
        }

        return 'I understand Copilot will execute this action immediately.';
    }

    private function unsafeActionConfirmLabel(string $actionType): string
    {
        return match ($actionType) {
            AiActionDraft::TYPE_APPROVE_INVOICE => 'Confirm payment release',
            AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process' => 'Confirm payment request',
            'award_quote' => 'Confirm supplier award',
            default => 'Confirm action',
        };
    }

    /**
     * @return list<string>
     */
    private function unsafeActionRisks(string $actionType): array
    {
        if ($actionType === AiActionDraft::TYPE_APPROVE_INVOICE) {
            return [
                'Updates the invoice status to Paid immediately.',
                'Posts payment details to the finance ledger.',
            ];
        }

        if (in_array($actionType, [AiActionDraft::TYPE_PAYMENT_DRAFT, 'payment_process'], true)) {
            return [
                'Creates a payment request ready for disbursement.',
                'May notify the supplier depending on AP automation rules.',
            ];
        }

        if ($actionType === 'award_quote') {
            return [
                'Commits the RFQ to the selected supplier and pricing.',
                'Downstream PO drafting will use this award immediately.',
            ];
        }

        return [];
    }

    private function formatActionLabel(string $value): string
    {
        $normalized = str_replace(['_', '-'], ' ', strtolower($value));

        return Str::title($normalized);
    }

    private function formatCurrencyAmount(mixed $amount, ?string $currency): ?string
    {
        $value = $this->floatValue($amount);

        if ($value === null) {
            return null;
        }

        $code = $currency !== null ? strtoupper($currency) : 'USD';

        return sprintf('%s %s', $code, number_format($value, 2, '.', ','));
    }

    private function runIntentPlanner(
        AiChatThread $thread,
        User $user,
        string $prompt,
        array $conversationHistory
    ): ?array {
        $plannerContext = $this->plannerContext($conversationHistory);
        if ($plannerContext === []) {
            $plannerContext = [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];
        }

        $payload = [
            'prompt' => $prompt,
            'context' => $plannerContext,
            'company_id' => $thread->company_id,
            'thread_id' => (string) $thread->id,
            'user_id' => $user->id,
        ];

        try {
            $response = $this->client->intentPlan($payload);
        } catch (AiServiceUnavailableException $exception) {
            report($exception);

            return null;
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            return null;
        }

        return $response['data'];
    }

    private function plannerContext(array $messages): array
    {
        $window = array_slice($messages, -12);
        $context = [];

        foreach ($window as $message) {
            $role = $message['role'] ?? null;
            $content = $message['content'] ?? null;

            if (! is_string($role) || ! is_string($content)) {
                continue;
            }

            $normalizedRole = strtolower(trim($role));

            if (! in_array($normalizedRole, ['user', 'assistant', 'system'], true)) {
                continue;
            }

            $trimmedContent = trim($content);
            if ($trimmedContent === '') {
                continue;
            }

            $context[] = [
                'role' => $normalizedRole,
                'content' => $trimmedContent,
            ];
        }

        return $context;
    }

    private function handlePlannerClarification(
        AiChatThread $thread,
        User $user,
        string $message,
        array $structuredContext,
        array $plan,
        AiChatMessage $userMessage
    ): ?array {
        $targetTool = $this->stringValue($plan['target_tool'] ?? null);
        if ($targetTool === null) {
            return null;
        }

        $missingArgs = $this->normalizeClarificationArgs($plan['missing_args'] ?? []);
        if ($missingArgs === []) {
            return null;
        }

        $question = $this->stringValue($plan['question'] ?? null) ?? 'Could you clarify one more detail?';
        $arguments = isset($plan['args']) && is_array($plan['args']) ? $plan['args'] : [];

        $clarificationPayload = [
            'id' => (string) Str::uuid(),
            'tool' => $targetTool,
            'question' => $question,
            'missing_args' => $missingArgs,
            'args' => $arguments,
        ];

        $assistantPayload = [
            'type' => 'clarification',
            'assistant_message_markdown' => $question,
            'citations' => [],
            'draft' => null,
            'workflow' => null,
            'tool_calls' => null,
            'needs_human_review' => false,
            'confidence' => 0.0,
            'warnings' => [],
            'clarification' => $clarificationPayload,
            'planner' => [
                'tool' => 'clarification',
                'args' => $arguments,
            ],
        ];

        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => $question,
            'content_json' => $assistantPayload,
            'citations_json' => [],
            'tool_calls_json' => [],
            'tool_results_json' => [],
            'latency_ms' => 0,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->recordChatEvent($thread, $user, $message, $structuredContext, 0, $assistantPayload, null);

        $this->storePendingClarification($thread, array_merge($clarificationPayload, [
            'prompt' => $message,
            'created_at' => now()->toIso8601String(),
        ]));

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    private function handleClarificationReply(
        AiChatThread $thread,
        User $user,
        string $message,
        array $structuredContext,
        array $clarificationContext,
        ?array $pendingClarification,
        AiChatMessage $userMessage
    ): ?array {
        $clarificationId = $this->stringValue($clarificationContext['id'] ?? null);
        if ($clarificationId === null) {
            return null;
        }

        $pending = $pendingClarification ?? $this->pendingClarification($thread);

        if ($pending === null) {
            return null;
        }

        $pendingId = $this->stringValue($pending['id'] ?? null);
        if ($pendingId === null || $pendingId !== $clarificationId) {
            return null;
        }

        $this->clearPendingClarification($thread);

        $toolName = $this->stringValue($pending['tool'] ?? null);
        if ($toolName === null) {
            return null;
        }

        $arguments = isset($pending['args']) && is_array($pending['args']) ? $pending['args'] : [];
        $missingArgs = $this->normalizeClarificationArgs($pending['missing_args'] ?? []);
        $primaryArg = $missingArgs[0] ?? null;

        $answer = trim($message) !== '' ? trim($message) : $message;
        if ($primaryArg !== null) {
            $arguments[$primaryArg] = $this->clarificationAnswerValue($answer);
        }

        $plan = [
            'tool' => $toolName,
            'args' => $arguments,
        ];

        $contextWithoutClarification = $structuredContext;
        unset($contextWithoutClarification['clarification']);

        $response = $this->handlePlannedTool(
            $thread,
            $user,
            $message,
            $contextWithoutClarification,
            $plan,
            $userMessage,
        );

        if ($response !== null) {
            return $response;
        }

        return null;
    }

    private function handleEntityPickerReply(
        AiChatThread $thread,
        User $user,
        string $message,
        array $structuredContext,
        array $pickerContext,
        ?array $pendingPicker,
        AiChatMessage $userMessage
    ): ?array {
        $pickerId = $this->stringValue($pickerContext['id'] ?? null);
        $candidateId = $this->stringValue($pickerContext['candidate_id'] ?? null);

        if ($pickerId === null || $candidateId === null) {
            return null;
        }

        $pending = $pendingPicker ?? $this->pendingEntityPicker($thread);

        if ($pending === null) {
            return null;
        }

        $pendingId = $this->stringValue($pending['id'] ?? null);

        if ($pendingId === null || $pendingId !== $pickerId) {
            return null;
        }

        $candidate = $this->matchPendingEntityCandidate($pending['candidates'] ?? null, $candidateId);

        if ($candidate === null) {
            return null;
        }

        $this->clearPendingEntityPicker($thread);

        $toolName = $this->stringValue($pending['target_tool'] ?? null);

        if ($toolName === null || $toolName === '') {
            return null;
        }

        $arguments = isset($candidate['args']) && is_array($candidate['args']) ? $candidate['args'] : [];
        $contextWithoutPicker = $structuredContext;
        unset($contextWithoutPicker['entity_picker']);

        $toolResponse = $this->resolveTools($thread, $user, [[
            'tool_name' => $toolName,
            'call_id' => (string) Str::uuid(),
            'arguments' => $arguments,
        ]], $contextWithoutPicker);

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $toolResponse['assistant_message'],
            'response' => $toolResponse['response'],
        ];
    }

    private function handlePlannedTool(
        AiChatThread $thread,
        User $user,
        string $message,
        array $structuredContext,
        array $plan,
        AiChatMessage $userMessage
    ): ?array {
        $toolName = isset($plan['tool']) && is_string($plan['tool']) ? trim($plan['tool']) : '';
        if ($toolName === '' || $toolName === 'clarification') {
            return null;
        }

        $arguments = isset($plan['args']) && is_array($plan['args']) ? $plan['args'] : [];

        if (isset(self::INTENT_TOOL_ACTION_MAP[$toolName])) {
            return $this->handlePlannerActionType(
                $thread,
                $user,
                $userMessage,
                $message,
                $structuredContext,
                self::INTENT_TOOL_ACTION_MAP[$toolName],
                $toolName,
                $arguments,
            );
        }

        if (in_array($toolName, self::INTENT_HELP_TOOLS, true)) {
            return $this->handlePlannerHelpTool(
                $thread,
                $user,
                $userMessage,
                $message,
                $structuredContext,
                $toolName,
                $arguments,
            );
        }

        return null;
    }

    private function handlePlannerPlan(
        AiChatThread $thread,
        User $user,
        string $message,
        array $structuredContext,
        array $plan,
        AiChatMessage $userMessage
    ): ?array {
        $steps = $this->normalizePlannerSteps($plan);

        if ($steps === []) {
            return null;
        }

        $finalResponse = null;

        foreach ($steps as $step) {
            $toolName = $this->stringValue($step['tool'] ?? null);

            if ($toolName === null || $toolName === '' || $toolName === 'plan') {
                continue;
            }

            if ($toolName === 'clarification') {
                $clarificationPlan = [
                    'tool' => 'clarification',
                    'target_tool' => $step['target_tool'] ?? null,
                    'missing_args' => $step['missing_args'] ?? [],
                    'question' => $step['question'] ?? null,
                    'args' => $step['args'] ?? [],
                ];

                return $this->handlePlannerClarification(
                    $thread,
                    $user,
                    $message,
                    $structuredContext,
                    $clarificationPlan,
                    $userMessage,
                );
            }

            $arguments = isset($step['args']) && is_array($step['args']) ? $step['args'] : [];

            if ($this->isWorkspaceToolName($toolName)) {
                $response = $this->handlePlannerWorkspaceToolStep(
                    $thread,
                    $user,
                    $structuredContext,
                    $toolName,
                    $arguments,
                    $userMessage,
                );
            } else {
                $response = $this->handlePlannedTool(
                    $thread,
                    $user,
                    $message,
                    $structuredContext,
                    [
                        'tool' => $toolName,
                        'args' => $arguments,
                    ],
                    $userMessage,
                );
            }

            if ($response !== null) {
                $finalResponse = $response;
            }
        }

        return $finalResponse;
    }

    private function handlePlannerWorkspaceToolStep(
        AiChatThread $thread,
        User $user,
        array $structuredContext,
        string $toolName,
        array $arguments,
        AiChatMessage $userMessage
    ): ?array {
        $toolCalls = [[
            'tool_name' => $toolName,
            'call_id' => (string) Str::uuid(),
            'arguments' => $arguments,
        ]];

        try {
            $result = $this->resolveTools($thread, $user, $toolCalls, $structuredContext);
        } catch (AiChatException | AiServiceUnavailableException $exception) {
            report($exception);

            return null;
        }

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $result['assistant_message'],
            'response' => $result['response'],
        ];
    }

    private function handlePlannerActionType(
        AiChatThread $thread,
        User $user,
        AiChatMessage $userMessage,
        string $latestPrompt,
        array $structuredContext,
        string $actionType,
        string $toolName,
        array $arguments
    ): ?array {
        $payload = [
            'company_id' => $thread->company_id,
            'action_type' => $actionType,
            'query' => $latestPrompt,
            'inputs' => $arguments,
            'user_context' => $this->chatUserContext($user),
        ];

        $startedAt = microtime(true);

        try {
            $response = $this->client->planAction($payload);
        } catch (AiServiceUnavailableException $exception) {
            report($exception);

            return null;
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            return null;
        }

        $draft = $response['data'];
        if (! isset($draft['action_type'])) {
            $draft['action_type'] = $actionType;
        }

        $assistantPayload = [
            'type' => 'draft_action',
            'assistant_message_markdown' => (string) ($draft['summary'] ?? sprintf('Draft ready for %s.', Str::title(str_replace('_', ' ', $actionType)))),
            'citations' => $this->normalizeList($draft['citations'] ?? []),
            'draft' => $draft,
            'workflow' => null,
            'tool_calls' => null,
            'needs_human_review' => (bool) ($draft['needs_human_review'] ?? true),
            'confidence' => (float) ($draft['confidence'] ?? 0.0),
            'warnings' => $this->normalizeList($draft['warnings'] ?? []),
            'planner' => [
                'tool' => $toolName,
                'args' => $arguments,
            ],
        ];

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

        $this->recordChatEvent($thread, $user, $latestPrompt, $structuredContext, $latency, $assistantPayload, null);

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    private function handlePlannerHelpTool(
        AiChatThread $thread,
        User $user,
        AiChatMessage $userMessage,
        string $latestPrompt,
        array $structuredContext,
        string $toolName,
        array $arguments
    ): ?array {
        $inputs = array_merge(['topic' => $arguments['topic'] ?? $latestPrompt], $arguments);
        $payload = [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'context' => [],
            'inputs' => $inputs,
        ];

        $startedAt = microtime(true);

        try {
            $response = $this->client->helpTool($payload);
        } catch (AiServiceUnavailableException $exception) {
            report($exception);

            return null;
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            return null;
        }

        $data = $response['data'];
        $payloadData = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
        $summary = (string) ($data['summary'] ?? ($payloadData['description'] ?? 'Here is a help guide.'));
        $steps = [];
        if (isset($payloadData['steps']) && is_array($payloadData['steps'])) {
            $steps = array_values(array_filter($payloadData['steps'], static fn ($step) => is_string($step) && $step !== ''));
        }

        if ($steps !== []) {
            $stepBullets = array_map(static fn ($step) => sprintf('- %s', $step), array_slice($steps, 0, 6));
            $summary = trim($summary . "\n" . implode("\n", $stepBullets));
        }

        $assistantPayload = [
            'type' => 'help',
            'assistant_message_markdown' => $summary,
            'citations' => $this->normalizeList($data['citations'] ?? []),
            'draft' => null,
            'workflow' => null,
            'tool_calls' => null,
            'needs_human_review' => false,
            'confidence' => 0.4,
            'warnings' => [],
            'help' => $payloadData,
            'planner' => [
                'tool' => $toolName,
                'args' => $arguments,
            ],
        ];

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $assistantMessage = $thread->appendMessage(AiChatMessage::ROLE_ASSISTANT, [
            'content_text' => $assistantPayload['assistant_message_markdown'],
            'content_json' => $assistantPayload,
            'citations_json' => $this->normalizeList($assistantPayload['citations'] ?? []),
            'tool_calls_json' => $this->normalizeList($assistantPayload['tool_calls'] ?? []),
            'tool_results_json' => $this->normalizeList($assistantPayload['tool_results'] ?? []),
            'latency_ms' => $latency,
            'status' => AiChatMessage::STATUS_COMPLETED,
        ]);

        $this->recordChatEvent($thread, $user, $latestPrompt, $structuredContext, $latency, $assistantPayload, null);

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
            'response' => $assistantPayload,
        ];
    }

    private function hasPlannerSteps(array $plan): bool
    {
        $nestedPlan = $plan['plan'] ?? null;

        if (is_array($nestedPlan) && isset($nestedPlan['steps']) && is_array($nestedPlan['steps']) && $nestedPlan['steps'] !== []) {
            return true;
        }

        return isset($plan['steps']) && is_array($plan['steps']) && $plan['steps'] !== [];
    }

    private function normalizePlannerSteps(array $plan): array
    {
        $rawSteps = null;

        if (isset($plan['plan']) && is_array($plan['plan']) && isset($plan['plan']['steps'])) {
            $rawSteps = $plan['plan']['steps'];
        } elseif (isset($plan['steps'])) {
            $rawSteps = $plan['steps'];
        }

        if (! is_array($rawSteps) || $rawSteps === []) {
            return [];
        }

        $steps = [];

        foreach ($rawSteps as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $tool = $this->stringValue($entry['tool'] ?? null);

            if ($tool === null || $tool === '') {
                continue;
            }

            $step = $entry;
            $step['tool'] = $tool;
            $step['args'] = isset($entry['args']) && is_array($entry['args']) ? $entry['args'] : [];

            $steps[] = $step;
        }

        return $steps;
    }

    private function isWorkspaceToolName(string $toolName): bool
    {
        return str_starts_with($toolName, 'workspace.');
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
        ?string $errorMessage,
        ?array $latencyBreakdown = null
    ): void {
        $requestPayload = [
            'thread_id' => $thread->id,
            'message_preview' => $this->formatMessagePreview($message),
            'context_meta' => $this->sanitizeContextForLogging($context),
        ];

        $status = $errorMessage === null
            ? AiEvent::STATUS_SUCCESS
            : AiEvent::STATUS_ERROR;

        $responsePayload = $this->attachLatencyBreakdown($assistantPayload, $latencyBreakdown);

        $this->recorder->record(
            companyId: $thread->company_id,
            userId: $user->id,
            feature: 'ai_chat_message_send',
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
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
        ?string $errorMessage,
        ?array $latencyBreakdown = null
    ): void {
        $requestPayload = [
            'thread_id' => $thread->id,
            'tool_calls' => $this->sanitizeToolCalls($toolCalls),
            'context_meta' => $this->sanitizeContextForLogging($context),
        ];

        $status = $errorMessage === null
            ? AiEvent::STATUS_SUCCESS
            : AiEvent::STATUS_ERROR;

        $responsePayload = $this->attachLatencyBreakdown($assistantPayload, $latencyBreakdown);

        $this->recorder->record(
            companyId: $thread->company_id,
            userId: $user->id,
            feature: 'ai_chat_tool_resolve',
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $latency,
            status: $status,
            errorMessage: $errorMessage,
            entityType: 'ai_chat_thread',
            entityId: $thread->id,
        );

        $helpCalls = $this->extractHelpToolCalls($toolCalls);

        if ($helpCalls !== []) {
            $this->recorder->record(
                companyId: $thread->company_id,
                userId: $user->id,
                feature: 'workspace_help',
                requestPayload: [
                    'thread_id' => $thread->id,
                    'help_calls' => $helpCalls,
                    'context_meta' => $this->sanitizeContextForLogging($context),
                ],
                responsePayload: $responsePayload,
                latencyMs: $latency,
                status: $status,
                errorMessage: $errorMessage,
                entityType: 'ai_chat_thread',
                entityId: $thread->id,
            );
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, mixed>|null $latencyBreakdown
     * @return array<string, mixed>|null
     */
    private function attachLatencyBreakdown(?array $payload, ?array $latencyBreakdown): ?array
    {
        if (! is_array($latencyBreakdown) || $latencyBreakdown === []) {
            return $payload;
        }

        $normalized = [];
        foreach ($latencyBreakdown as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $normalized[$key] = max(0, (int) round((float) $value));

                continue;
            }

            if (is_string($value) && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        if ($normalized === []) {
            return $payload;
        }

        $result = is_array($payload) ? $payload : [];
        $result['latency_breakdown_ms'] = $normalized;

        return $result;
    }

    /**
     * @return array<string, int|string>|null
     */
    private function buildLatencyBreakdownTelemetry(
        string $operation,
        string $mode,
        ?int $totalMs,
        ?int $providerMs,
        ?int $appMs
    ): ?array {
        $telemetry = [
            'operation' => $operation,
            'mode' => $mode,
        ];

        if ($totalMs !== null) {
            $telemetry['total_ms'] = max(0, $totalMs);
        }

        if ($providerMs !== null) {
            $telemetry['provider_ms'] = max(0, $providerMs);
        }

        if ($appMs !== null) {
            $telemetry['app_ms'] = max(0, $appMs);
        }

        return isset($telemetry['total_ms']) || isset($telemetry['provider_ms']) || isset($telemetry['app_ms'])
            ? $telemetry
            : null;
    }

    /**
     * @param array<int, array<string, mixed>> $toolCalls
     * @return array<int, array<string, mixed>>
     */
    private function extractHelpToolCalls(array $toolCalls): array
    {
        $entries = [];

        foreach ($toolCalls as $call) {
            $toolName = (string) ($call['tool_name'] ?? '');

            if ($toolName !== AiChatToolCall::Help->value) {
                continue;
            }

            $entry = [
                'tool_name' => $toolName,
                'call_id' => isset($call['call_id']) ? (string) $call['call_id'] : null,
            ];

            if (isset($call['arguments']) && is_array($call['arguments'])) {
                $arguments = [];

                foreach (['topic', 'query', 'action'] as $field) {
                    $value = $call['arguments'][$field] ?? null;

                    if (is_string($value)) {
                        $trimmed = trim($value);

                        if ($trimmed !== '') {
                            $arguments[$field] = $trimmed;
                        }
                    }
                }

                if ($arguments !== []) {
                    $entry['arguments'] = $arguments;
                }
            }

            $entries[] = array_filter($entry, static fn ($value) => $value !== null && $value !== '');
        }

        return $entries;
    }
}

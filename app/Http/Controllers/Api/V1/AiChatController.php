<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AiChatToolCall;
use App\Exceptions\AiChatException;
use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Ai\AiChatCreateThreadRequest;
use App\Http\Requests\Api\Ai\AiChatResolveToolsRequest;
use App\Http\Requests\Api\Ai\AiChatSendMessageRequest;
use App\Http\Resources\AiChatMessageResource;
use App\Http\Resources\AiChatThreadResource;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\Ai\ChatService;
use App\Services\Ai\AiEventRecorder;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends ApiController
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly AiEventRecorder $recorder,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to use Copilot chat.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $query = AiChatThread::query()
            ->forCompany($companyId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $statuses = $this->normalizeStatuses($request->query('status'));

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $paginator = $query->cursorPaginate(
            perPage: $this->perPage($request, 15, 50),
            columns: ['*'],
            cursorName: 'cursor',
            cursor: $request->query('cursor')
        );

        if ($request->query()) {
            $paginator->appends(collect($request->query())->except('cursor')->all());
        }

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, AiChatThreadResource::class);

        $this->recorder->record(
            companyId: $companyId,
            userId: $user->id,
            feature: 'ai_chat_threads_index',
            requestPayload: [
                'status' => $statuses,
                'cursor' => $request->query('cursor'),
            ],
            responsePayload: [
                'count' => count($items),
            ],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: 'ai_chat_thread',
            entityId: null,
        );

        return $this->ok([
            'items' => $items,
        ], 'Chat threads loaded.', $meta);
    }

    public function store(AiChatCreateThreadRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to create chat threads.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $thread = $this->chatService->createThread($companyId, $user, $request->title());

        return $this->ok([
            'thread' => (new AiChatThreadResource($thread))->toArray($request),
        ], 'Chat thread created.');
    }

    public function show(Request $request, int $thread): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to view this thread.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $limit = $this->messageLimit($request);
        $model = $this->chatService->getThreadWithMessages($thread, $companyId, $limit);

        if (! $model instanceof AiChatThread) {
            return $this->fail('Thread not found.', Response::HTTP_NOT_FOUND);
        }

        $response = $this->ok([
            'thread' => (new AiChatThreadResource($model))->toArray($request),
        ], 'Chat thread loaded.');

        $messageCount = $model->relationLoaded('messages') ? $model->messages->count() : null;

        $this->recorder->record(
            companyId: $companyId,
            userId: $user->id,
            feature: 'ai_chat_thread_show',
            requestPayload: [
                'thread_id' => $thread,
                'limit' => $limit,
            ],
            responsePayload: [
                'message_count' => $messageCount,
            ],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: 'ai_chat_thread',
            entityId: $model->id,
        );

        return $response;
    }

    public function send(AiChatSendMessageRequest $request, int $thread): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to send chat messages.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $model = $this->chatService->getThreadWithMessages($thread, $companyId, $this->messageLimit($request));

        if (! $model instanceof AiChatThread) {
            return $this->fail('Thread not found.', Response::HTTP_NOT_FOUND);
        }

        if ($request->wantsStream()) {
            try {
                $result = $this->chatService->prepareStream($model, $user, $request->message(), $request->messageContext());
            } catch (AiChatException $exception) {
                return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY, $exception->errors());
            }

            return $this->ok([
                'user_message' => (new AiChatMessageResource($result['user_message']))->toArray($request),
                'stream_token' => $result['stream_token'],
                'expires_in' => $result['expires_in'],
            ], 'Streaming session ready.');
        }

        try {
            $result = $this->chatService->sendMessage($model, $user, $request->message(), $request->messageContext());
        } catch (AiServiceUnavailableException $exception) {
            $this->incrementToolErrorCount($request);
            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => [$exception->getMessage()],
            ]);
        } catch (AiChatException $exception) {
            $this->incrementToolErrorCount($request);
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY, $exception->errors());
        }

        return $this->ok([
            'user_message' => (new AiChatMessageResource($result['user_message']))->toArray($request),
            'assistant_message' => (new AiChatMessageResource($result['assistant_message']))->toArray($request),
            'response' => $result['response'],
        ], 'Message processed.');
    }

    public function stream(Request $request, int $thread): StreamedResponse|JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to stream chat messages.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $token = (string) $request->query('token', '');

        if ($token === '') {
            return $this->fail('Stream token is required.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'token' => ['Stream token is required.'],
            ]);
        }

        $model = AiChatThread::query()
            ->forCompany($companyId)
            ->whereKey($thread)
            ->first();

        if (! $model instanceof AiChatThread) {
            return $this->fail('Thread not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $session = $this->chatService->claimStreamSession($model, $user, $token);
        } catch (AiChatException $exception) {
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_REQUEST, [
                'token' => [$exception->getMessage()],
            ]);
        }

        return response()->stream(function () use ($model, $user, $session): void {
            $this->chatService->runStreamSession($model, $user, $session, static function (string $frame): void {
                echo $frame;

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                if (function_exists('flush')) {
                    @flush();
                }
            });
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function resolveTools(AiChatResolveToolsRequest $request, int $thread): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesChatAccess($user, $companyId)) {
            return $this->fail('You are not authorized to resolve workspace tools.', Response::HTTP_FORBIDDEN, [
                'code' => 'ai_chat_forbidden',
            ]);
        }

        $toolCalls = $request->toolCalls();

        if ($this->requiresHelpPermission($toolCalls) && ! $this->permissionRegistry->userHasAny($user, ['help.read'], $companyId)) {
            return $this->fail('You are not authorized to view workspace guides.', Response::HTTP_FORBIDDEN, [
                'code' => 'workspace_help_forbidden',
            ]);
        }

        if ($this->requiresDisputeDraftPermission($toolCalls) && ! $this->permissionRegistry->userHasAny($user, ['billing.write'], $companyId)) {
            return $this->fail('You are not authorized to draft invoice disputes.', Response::HTTP_FORBIDDEN, [
                'code' => 'workspace_dispute_draft_forbidden',
            ]);
        }

        $model = $this->chatService->getThreadWithMessages($thread, $companyId, $this->messageLimit($request));

        if (! $model instanceof AiChatThread) {
            return $this->fail('Thread not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->chatService->resolveTools($model, $user, $toolCalls, $request->messageContext());
        } catch (AiServiceUnavailableException $exception) {
            $this->incrementToolErrorCount($request);
            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => [$exception->getMessage()],
            ]);
        } catch (AiChatException $exception) {
            $this->incrementToolErrorCount($request);
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY, $exception->errors());
        }

        return $this->ok([
            'tool_message' => (new AiChatMessageResource($result['tool_message']))->toArray($request),
            'assistant_message' => (new AiChatMessageResource($result['assistant_message']))->toArray($request),
            'response' => $result['response'],
        ], 'Workspace tools resolved.');
    }

    private function messageLimit(Request $request): int
    {
        $limit = (int) $request->query('limit', config('ai_chat.history_limit', 30));

        if ($limit < 1) {
            $limit = 1;
        }

        return min($limit, 100);
    }

    private function deniesChatAccess(User $user, int $companyId): bool
    {
        $permissions = config('ai_chat.permissions', ['ai.workflows.run']);

        return $permissions !== [] && ! $this->permissionRegistry->userHasAny($user, $permissions, $companyId);
    }

    /**
     * @return list<string>
     */
    private function normalizeStatuses(mixed $status): array
    {
        if ($status === null || $status === '') {
            return [];
        }

        $values = is_array($status) ? $status : explode(',', (string) $status);
        $allowed = AiChatThread::STATUSES;

        return collect($values)
            ->filter(static fn ($value) => is_string($value))
            ->map(static fn ($value) => strtolower(trim($value)))
            ->filter(static fn ($value) => $value !== '' && in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function incrementToolErrorCount(Request $request): void
    {
        try {
            $request->session()->increment('tool_error_count');
        } catch (\Throwable) {
            // Session may not be available for stateless clients; ignore silently.
        }
    }

    /**
     * @param list<array{tool_name:string,call_id:string,arguments?:array<string,mixed>}>|list<array{tool_name:string,call_id:string}> $toolCalls
     */
    private function requiresHelpPermission(array $toolCalls): bool
    {
        foreach ($toolCalls as $call) {
            $toolName = (string) ($call['tool_name'] ?? '');

            if ($toolName === AiChatToolCall::Help->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{tool_name:string,call_id:string,arguments?:array<string,mixed>}>|list<array{tool_name:string,call_id:string}> $toolCalls
     */
    private function requiresDisputeDraftPermission(array $toolCalls): bool
    {
        foreach ($toolCalls as $call) {
            $toolName = (string) ($call['tool_name'] ?? '');

            if ($toolName === AiChatToolCall::CreateDisputeDraft->value) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Admin\UpdateWebhookSubscriptionRequest;
use App\Http\Requests\Admin\TestWebhookSubscriptionRequest;
use App\Http\Resources\Admin\WebhookSubscriptionResource;
use App\Models\WebhookSubscription;
use App\Services\Admin\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class WebhookSubscriptionController extends Controller
{
    public function __construct(private readonly WebhookService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookSubscription::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $subscriptions = WebhookSubscription::query()
            ->with('company:id,name')
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($subscriptions, 'Webhook subscriptions retrieved.');
    }

    public function store(StoreWebhookSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->service->createSubscription($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook subscription created.',
            'data' => [
                'subscription' => WebhookSubscriptionResource::make($subscription),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->authorize('view', $webhookSubscription);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook subscription retrieved.',
            'data' => [
                'subscription' => WebhookSubscriptionResource::make($webhookSubscription->loadMissing('company:id,name')),
            ],
        ]);
    }

    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $subscription = $this->service->updateSubscription($webhookSubscription, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook subscription updated.',
            'data' => [
                'subscription' => WebhookSubscriptionResource::make($subscription),
            ],
        ]);
    }

    public function destroy(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->authorize('delete', $webhookSubscription);

        $this->service->deleteSubscription($webhookSubscription);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook subscription deleted.',
            'data' => null,
        ]);
    }

    public function test(TestWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $payload = $request->validated();

        $this->service->sendTestEvent($webhookSubscription, $payload['event'], $payload['payload'] ?? []);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook test event queued.',
            'data' => null,
        ]);
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
        $items = WebhookSubscriptionResource::collection(collect($paginator->items()))->resolve(request());

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'items' => $items,
                'meta' => [
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}

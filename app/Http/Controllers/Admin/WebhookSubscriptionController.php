<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Admin\UpdateWebhookSubscriptionRequest;
use App\Http\Requests\Admin\TestWebhookSubscriptionRequest;
use App\Http\Resources\Admin\WebhookSubscriptionResource;
use App\Models\WebhookSubscription;
use App\Services\Admin\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookSubscriptionController extends ApiController
{
    public function __construct(private readonly WebhookService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookSubscription::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = WebhookSubscription::query()
            ->with('company:id,name')
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, WebhookSubscriptionResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Webhook subscriptions retrieved.', $paginated['meta']);
    }

    public function store(StoreWebhookSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->service->createSubscription($request->validated());

        $response = $this->ok([
            'subscription' => (new WebhookSubscriptionResource($subscription))->toArray($request),
        ], 'Webhook subscription created.');

        return $response->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->authorize('view', $webhookSubscription);

        return $this->ok([
            'subscription' => (new WebhookSubscriptionResource($webhookSubscription->loadMissing('company:id,name')))->toArray($request),
        ], 'Webhook subscription retrieved.');
    }

    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $subscription = $this->service->updateSubscription($webhookSubscription, $request->validated());

        return $this->ok([
            'subscription' => (new WebhookSubscriptionResource($subscription))->toArray($request),
        ], 'Webhook subscription updated.');
    }

    public function destroy(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->authorize('delete', $webhookSubscription);

        $this->service->deleteSubscription($webhookSubscription);

        return $this->ok(null, 'Webhook subscription deleted.');
    }

    public function test(TestWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $payload = $request->validated();

        $this->service->sendTestEvent($webhookSubscription, $payload['event'], $payload['payload'] ?? []);

        return $this->ok(null, 'Webhook test event queued.');
    }
}

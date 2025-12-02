<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\RetryWebhookDeliveryRequest;
use App\Http\Resources\Admin\WebhookDeliveryResource;
use App\Models\WebhookDelivery;
use App\Services\Admin\WebhookService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WebhookDeliveryController extends ApiController
{
    public function __construct(private readonly WebhookService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookDelivery::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = WebhookDelivery::query()
            ->with('subscription:id,company_id,url')
            ->when($request->filled('subscription_id'), fn (Builder $query) => $query->where('subscription_id', $request->input('subscription_id')))
            ->when($request->filled('company_id'), function (Builder $query) use ($request): void {
                $query->whereHas('subscription', fn (Builder $builder) => $builder->where('company_id', $request->input('company_id')));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, WebhookDeliveryResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Webhook deliveries retrieved.', $paginated['meta']);
    }

    public function retry(RetryWebhookDeliveryRequest $request, WebhookDelivery $delivery): JsonResponse
    {
        $this->service->retryDelivery($delivery);

        return $this->ok(null, 'Webhook delivery re-queued.');
    }
}

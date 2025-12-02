<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\EventDeliveryIndexRequest;
use App\Http\Requests\Events\ReplayDeadLettersRequest;
use App\Http\Resources\EventDeliveryResource;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\EventDeliveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventDeliveryController extends ApiController
{
    public function __construct(private readonly EventDeliveryService $service)
    {
    }

    public function index(EventDeliveryIndexRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'viewAny', WebhookDelivery::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if (! $user->isPlatformAdmin() && $companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $filters = $request->filters();
        $perPage = $filters['per_page'] ?? $this->perPage($request, 25, 100);
        $cursor = $filters['cursor'] ?? null;

        $query = WebhookDelivery::query()
            ->with(['subscription:id,company_id,url'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $user->isPlatformAdmin()) {
            $query->where('company_id', (int) $companyId);
        }

        if (! empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['endpoint'])) {
            $endpoint = $filters['endpoint'];
            $query->whereHas('subscription', function (Builder $builder) use ($endpoint): void {
                $builder->where('url', 'like', "%{$endpoint}%");
            });
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('event', 'like', "%{$term}%")
                    ->orWhere('last_error', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['dlq_only'])) {
            $query->whereNotNull('dead_lettered_at');
        }

        $paginator = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor)->withQueryString();

        $paginated = $this->paginate($paginator, $request, EventDeliveryResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], null, $paginated['meta']);
    }

    public function retry(Request $request, WebhookDelivery $delivery): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->deliveryVisibleToUser($user, $delivery)) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'retry', $delivery)) {
            return $this->fail('Forbidden.', 403);
        }

        $this->service->retryDelivery($delivery, $user);

        return $this->ok(['id' => $delivery->id], 'Delivery re-queued.');
    }

    public function replay(ReplayDeadLettersRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'replay', WebhookDelivery::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $ids = $request->ids();

        $query = WebhookDelivery::query()
            ->whereIn('id', $ids)
            ->whereNotNull('dead_lettered_at');

        $companyId = $this->resolveUserCompanyId($user);

        if (! $user->isPlatformAdmin()) {
            if ($companyId === null) {
                return $this->fail('Company context required.', 403);
            }

            $query->where('company_id', $companyId);
        }

        $deliveries = $query->get();

        if ($deliveries->isEmpty()) {
            return $this->fail('No deliveries available for replay.', 404);
        }

        $count = $this->service->replayDeadLetters($deliveries, $user);

        return $this->ok([
            'replayed' => $count,
        ], 'Dead letter deliveries re-queued.');
    }

    private function deliveryVisibleToUser(User $user, WebhookDelivery $delivery): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return false;
        }

        return (int) ($delivery->company_id ?? 0) === $companyId;
    }
}

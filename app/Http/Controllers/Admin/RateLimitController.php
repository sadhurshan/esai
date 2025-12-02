<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreRateLimitRequest;
use App\Http\Requests\Admin\UpdateRateLimitRequest;
use App\Http\Resources\Admin\RateLimitResource;
use App\Models\RateLimit;
use App\Services\Admin\RateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitController extends ApiController
{
    public function __construct(private readonly RateLimitService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RateLimit::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = RateLimit::query()
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, RateLimitResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Rate limits retrieved.', $paginated['meta']);
    }

    public function store(StoreRateLimitRequest $request): JsonResponse
    {
        $rateLimit = $this->service->create($request->validated());

        $response = $this->ok([
            'rate_limit' => (new RateLimitResource($rateLimit))->toArray($request),
        ], 'Rate limit created.');

        return $response->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(RateLimit $rateLimit): JsonResponse
    {
        $this->authorize('view', $rateLimit);

        return $this->ok([
            'rate_limit' => (new RateLimitResource($rateLimit))->toArray($request),
        ], 'Rate limit retrieved.');
    }

    public function update(UpdateRateLimitRequest $request, RateLimit $rateLimit): JsonResponse
    {
        $rateLimit = $this->service->update($rateLimit, $request->validated());

        return $this->ok([
            'rate_limit' => (new RateLimitResource($rateLimit))->toArray($request),
        ], 'Rate limit updated.');
    }

    public function destroy(RateLimit $rateLimit): JsonResponse
    {
        $this->authorize('delete', $rateLimit);

        $this->service->delete($rateLimit);

        return $this->ok(null, 'Rate limit deleted.');
    }
}

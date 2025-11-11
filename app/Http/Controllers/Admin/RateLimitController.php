<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRateLimitRequest;
use App\Http\Requests\Admin\UpdateRateLimitRequest;
use App\Http\Resources\Admin\RateLimitResource;
use App\Models\RateLimit;
use App\Services\Admin\RateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class RateLimitController extends Controller
{
    public function __construct(private readonly RateLimitService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RateLimit::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $limits = RateLimit::query()
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($limits, 'Rate limits retrieved.');
    }

    public function store(StoreRateLimitRequest $request): JsonResponse
    {
        $rateLimit = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Rate limit created.',
            'data' => [
                'rate_limit' => RateLimitResource::make($rateLimit),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(RateLimit $rateLimit): JsonResponse
    {
        $this->authorize('view', $rateLimit);

        return response()->json([
            'status' => 'success',
            'message' => 'Rate limit retrieved.',
            'data' => [
                'rate_limit' => RateLimitResource::make($rateLimit),
            ],
        ]);
    }

    public function update(UpdateRateLimitRequest $request, RateLimit $rateLimit): JsonResponse
    {
        $rateLimit = $this->service->update($rateLimit, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Rate limit updated.',
            'data' => [
                'rate_limit' => RateLimitResource::make($rateLimit),
            ],
        ]);
    }

    public function destroy(RateLimit $rateLimit): JsonResponse
    {
        $this->authorize('delete', $rateLimit);

        $this->service->delete($rateLimit);

        return response()->json([
            'status' => 'success',
            'message' => 'Rate limit deleted.',
            'data' => null,
        ]);
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
        $items = RateLimitResource::collection(collect($paginator->items()))->resolve(request());

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

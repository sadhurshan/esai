<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\Admin\PlanResource;
use App\Models\Plan;
use App\Services\Admin\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $planService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $plans = Plan::query()
            ->orderBy('id')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($plans, 'Plans retrieved.');
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Plan created.',
            'data' => [
                'plan' => PlanResource::make($plan),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return response()->json([
            'status' => 'success',
            'message' => 'Plan retrieved.',
            'data' => [
                'plan' => PlanResource::make($plan),
            ],
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->update($plan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Plan updated.',
            'data' => [
                'plan' => PlanResource::make($plan),
            ],
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $this->planService->delete($plan);

        return response()->json([
            'status' => 'success',
            'message' => 'Plan deleted.',
            'data' => null,
        ]);
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
    $items = PlanResource::collection(collect($paginator->items()))->resolve(request());

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

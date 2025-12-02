<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\Admin\PlanResource;
use App\Models\Plan;
use App\Services\Admin\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends ApiController
{
    public function __construct(private readonly PlanService $planService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = Plan::query()
            ->orderBy('id')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, PlanResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Plans retrieved.', $paginated['meta']);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create($request->validated());

        $response = $this->ok([
            'plan' => (new PlanResource($plan))->toArray($request),
        ], 'Plan created.');

        return $response->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return $this->ok([
            'plan' => (new PlanResource($plan))->toArray($request),
        ], 'Plan retrieved.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->update($plan, $request->validated());

        return $this->ok([
            'plan' => (new PlanResource($plan))->toArray($request),
        ], 'Plan updated.');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $this->planService->delete($plan);

        return $this->ok(null, 'Plan deleted.');
    }
}

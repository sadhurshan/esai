<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreDigitalTwinCategoryRequest;
use App\Http\Requests\Admin\UpdateDigitalTwinCategoryRequest;
use App\Http\Resources\DigitalTwinCategoryResource;
use App\Models\DigitalTwinCategory;
use App\Services\DigitalTwin\DigitalTwinCategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DigitalTwinCategoryController extends ApiController
{
    public function __construct(private readonly DigitalTwinCategoryService $categoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DigitalTwinCategory::class);

        if ($request->boolean('tree')) {
            $categories = DigitalTwinCategory::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->with(['children' => $this->recursiveChildrenLoader()])
                ->get();

            $items = DigitalTwinCategoryResource::collection($categories)->toArray($request);

            return $this->ok([
                'items' => $items,
            ], 'Digital twin categories retrieved.');
        }

        $perPage = $this->perPage($request, 50, 100);

        $paginator = DigitalTwinCategory::query()
            ->orderBy('name')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, DigitalTwinCategoryResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Digital twin categories retrieved.', $paginated['meta']);
    }

    public function store(StoreDigitalTwinCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', DigitalTwinCategory::class);

        $category = $this->categoryService->create($request->validated());

        return $this->ok([
            'category' => (new DigitalTwinCategoryResource($category))->toArray($request),
        ], 'Digital twin category created.')->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, DigitalTwinCategory $digitalTwinCategory): JsonResponse
    {
        $this->authorize('view', $digitalTwinCategory);

        return $this->ok([
            'category' => (new DigitalTwinCategoryResource($digitalTwinCategory->load(['parent', 'children'])))->toArray($request),
        ], 'Digital twin category retrieved.');
    }

    public function update(UpdateDigitalTwinCategoryRequest $request, DigitalTwinCategory $digitalTwinCategory): JsonResponse
    {
        $this->authorize('update', $digitalTwinCategory);

        $category = $this->categoryService->update($digitalTwinCategory, $request->validated());

        return $this->ok([
            'category' => (new DigitalTwinCategoryResource($category))->toArray($request),
        ], 'Digital twin category updated.');
    }

    public function destroy(DigitalTwinCategory $digitalTwinCategory): JsonResponse
    {
        $this->authorize('delete', $digitalTwinCategory);

        try {
            $this->categoryService->delete($digitalTwinCategory);
        } catch (ValidationException $validationException) {
            return $this->fail('Unable to delete digital twin category.', Response::HTTP_UNPROCESSABLE_ENTITY, $validationException->errors());
        }

        return $this->ok(null, 'Digital twin category deleted.');
    }

    private function recursiveChildrenLoader(): callable
    {
        return function (Builder|HasMany $query): void {
            $query->orderBy('name')->with(['children' => $this->recursiveChildrenLoader()]);
        };
    }

}

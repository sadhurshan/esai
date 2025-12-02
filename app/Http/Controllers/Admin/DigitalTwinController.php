<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DigitalTwinStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreDigitalTwinRequest;
use App\Http\Requests\Admin\UpdateDigitalTwinRequest;
use App\Http\Resources\DigitalTwinListResource;
use App\Http\Resources\DigitalTwinResource;
use App\Models\DigitalTwin;
use App\Models\User;
use App\Services\DigitalTwin\DigitalTwinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DigitalTwinController extends ApiController
{
    public function __construct(private readonly DigitalTwinService $digitalTwinService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DigitalTwin::class);

        $perPage = $this->perPage($request, 25, 100);

        $query = DigitalTwin::query()
            ->with(['category'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $statusEnum = DigitalTwinStatus::tryFrom($status);
            if ($statusEnum) {
                $query->where('status', $statusEnum->value);
            }
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($text = $request->query('q')) {
            $query->where(function ($builder) use ($text): void {
                $builder->where('title', 'like', "%{$text}%")
                    ->orWhere('summary', 'like', "%{$text}%")
                    ->orWhere('tags_search', 'like', '%'.strtolower($text).'%');
            });
        }

        $paginator = $query->cursorPaginate($perPage)->withQueryString();

        $paginated = $this->paginate($paginator, $request, DigitalTwinListResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Digital twins retrieved.', $paginated['meta']);
    }

    public function store(StoreDigitalTwinRequest $request): JsonResponse
    {
        $this->authorize('create', DigitalTwin::class);

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $twin = $this->digitalTwinService->create($actor, $request->validated());

        return $this->ok([
            'digital_twin' => (new DigitalTwinResource($twin))->toArray($request),
        ], 'Digital twin created.')->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DigitalTwin $digitalTwin, Request $request): JsonResponse
    {
        $this->authorize('view', $digitalTwin);

        $digitalTwin->load(['category', 'specs', 'assets']);

        return $this->ok([
            'digital_twin' => (new DigitalTwinResource($digitalTwin))->toArray($request),
        ], 'Digital twin retrieved.');
    }

    public function update(UpdateDigitalTwinRequest $request, DigitalTwin $digitalTwin): JsonResponse
    {
        $this->authorize('update', $digitalTwin);

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $twin = $this->digitalTwinService->update($actor, $digitalTwin, $request->validated());

        return $this->ok([
            'digital_twin' => (new DigitalTwinResource($twin))->toArray($request),
        ], 'Digital twin updated.');
    }

    public function destroy(DigitalTwin $digitalTwin, Request $request): JsonResponse
    {
        $this->authorize('delete', $digitalTwin);

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $this->digitalTwinService->delete($actor, $digitalTwin);

        return $this->ok(null, 'Digital twin deleted.');
    }

    public function publish(Request $request, DigitalTwin $digitalTwin): JsonResponse
    {
        $this->authorize('update', $digitalTwin);

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $twin = $this->digitalTwinService->publish($actor, $digitalTwin);

        return $this->ok([
            'digital_twin' => (new DigitalTwinResource($twin->load(['category', 'specs'])))->toArray($request),
        ], 'Digital twin published.');
    }

    public function archive(Request $request, DigitalTwin $digitalTwin): JsonResponse
    {
        $this->authorize('update', $digitalTwin);

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $twin = $this->digitalTwinService->archive($actor, $digitalTwin);

        return $this->ok([
            'digital_twin' => (new DigitalTwinResource($twin->load(['category', 'specs'])))->toArray($request),
        ], 'Digital twin archived.');
    }

    /**
     * @return User|JsonResponse
     */
    private function resolveActor(Request $request): User|JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        return $user;
    }
}

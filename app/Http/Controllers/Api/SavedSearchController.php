<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Search\StoreSavedSearchRequest;
use App\Http\Requests\Search\UpdateSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends ApiController
{
    public function __construct(private readonly GlobalSearchService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if (! $companyId) {
            return $this->fail('Company context required.', 403);
        }

        $perPage = $this->perPage($request, 25, 100);

        $paginator = SavedSearch::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, SavedSearchResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Saved searches retrieved.', $paginated['meta']);
    }

    public function store(StoreSavedSearchRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if (! $companyId) {
            return $this->fail('Company context required.', 403);
        }

        if ($this->nameExists($request->input('name'), $companyId, $user->id)) {
            return $this->fail('Saved search name already in use.', 422, [
                'name' => ['Name must be unique per user.'],
            ]);
        }

        $entityTypes = $request->input('entity_types');
        if (! is_array($entityTypes) || $entityTypes === []) {
            $entityTypes = $this->service->getAllowedEntityTypesForUser($user);
        }

        $filters = $request->input('filters');
        if (! is_array($filters)) {
            $filters = [];
        }

        $saved = SavedSearch::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'query' => $request->input('q'),
            'entity_types' => array_values($entityTypes),
            'filters' => $filters,
            'tags' => $request->input('tags'),
        ]);

        return $this->ok(
            (new SavedSearchResource($saved))->toArray($request),
            'Saved search created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccess($savedSearch, $user)) {
            return $this->fail('Saved search not accessible.', 403);
        }

        return $this->ok((new SavedSearchResource($savedSearch))->toArray($request));
    }

    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccess($savedSearch, $user)) {
            return $this->fail('Saved search not accessible.', 403);
        }

        $name = $request->input('name', $savedSearch->name);

        if ($name !== $savedSearch->name && $this->nameExists($name, (int) $savedSearch->company_id, (int) $savedSearch->user_id)) {
            return $this->fail('Saved search name already in use.', 422, [
                'name' => ['Name must be unique per user.'],
            ]);
        }

        if ($request->filled('name')) {
            $savedSearch->name = $name;
        }

        if ($request->filled('q')) {
            $savedSearch->query = (string) $request->input('q');
        }

        if ($request->has('entity_types')) {
            $entityTypes = $request->input('entity_types');
            $savedSearch->entity_types = is_array($entityTypes) && $entityTypes !== []
                ? array_values($entityTypes)
                : $this->service->getAllowedEntityTypesForUser($user);
        }

        if ($request->has('filters')) {
            $filters = $request->input('filters');
            $savedSearch->filters = is_array($filters) ? $filters : [];
        }

        if ($request->has('tags')) {
            $savedSearch->tags = $request->input('tags');
        }

        $savedSearch->save();

        return $this->ok((new SavedSearchResource($savedSearch))->toArray($request), 'Saved search updated.');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccess($savedSearch, $user)) {
            return $this->fail('Saved search not accessible.', 403);
        }

        $savedSearch->delete();

        return $this->ok(null, 'Saved search deleted.');
    }

    private function nameExists(string $name, int $companyId, int $userId): bool
    {
        return SavedSearch::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('name', $name)
            ->exists();
    }

    private function canAccess(SavedSearch $savedSearch, User $user): bool
    {
        $companyId = $this->resolveUserCompanyId($user);

        if (! $companyId) {
            return false;
        }

        return (int) $savedSearch->company_id === $companyId
            && (int) $savedSearch->user_id === (int) $user->id;
    }
}

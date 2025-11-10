<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\StoreLocationRequest;
use App\Http\Requests\DigitalTwin\UpdateLocationRequest;
use App\Http\Resources\DigitalTwin\LocationResource;
use App\Models\Location;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocationController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Location::class);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        $companyId = (int) $request->user()->company_id;
        $perPage = $this->perPage($request, 25, 100);

        $query = Location::query()
            ->where('company_id', $companyId)
            ->withCount(['systems', 'assets'])
            ->orderBy('name');

        if (isset($validated['parent_id'])) {
            $query->where('parent_id', $validated['parent_id']);
        }

        if (! empty($validated['search'])) {
            $term = Str::lower($validated['search']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%$term%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%$term%"]); // TODO: clarify with spec if fulltext needed
            });
        }

        $locations = $query->cursorPaginate($perPage, ['*'], 'cursor', $validated['cursor'] ?? null);

        $items = collect($locations->items())
            ->map(fn (Location $location) => (new LocationResource($location))->toArray($request))
            ->values()
            ->all();

        return $this->ok(
            ['items' => $items],
            'Locations retrieved.',
            [
                'next_cursor' => optional($locations->nextCursor())->encode(),
                'prev_cursor' => optional($locations->previousCursor())->encode(),
            ]
        );
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = (int) $request->user()->company_id;

        $location = Location::create($data)->loadCount(['systems', 'assets']);
        $this->auditLogger->created($location, Arr::only($location->getAttributes(), array_keys($data)));

        return $this->ok(
            (new LocationResource($location))->toArray($request),
            'Location created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, Location $location): JsonResponse
    {
        $this->authorize('view', $location);

        $location->loadCount(['systems', 'assets']);

        return $this->ok(
            (new LocationResource($location))->toArray($request),
            'Location retrieved.'
        );
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $data = $request->validated();

        if ($data !== []) {
            $before = Arr::only($location->getOriginal(), array_keys($data));
            $location->fill($data);
            $location->save();
            $this->auditLogger->updated($location, $before, Arr::only($location->getAttributes(), array_keys($data)));
        }

        $location->loadCount(['systems', 'assets']);

        return $this->ok(
            (new LocationResource($location))->toArray($request),
            'Location updated.'
        );
    }

    public function destroy(Location $location): JsonResponse
    {
        $this->authorize('delete', $location);

        $before = $location->getAttributes();
        $location->delete();
        $this->auditLogger->deleted($location, $before);

        return $this->ok(null, 'Location removed.');
    }
}

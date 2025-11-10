<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\StoreSystemRequest;
use App\Http\Requests\DigitalTwin\UpdateSystemRequest;
use App\Http\Resources\DigitalTwin\SystemResource;
use App\Models\System;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SystemController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', System::class);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'location_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        $companyId = (int) $request->user()->company_id;
        $perPage = $this->perPage($request, 25, 100);

        $query = System::query()
            ->where('company_id', $companyId)
            ->with(['location:id,name,code'])
            ->withCount('assets')
            ->orderBy('name');

        if (isset($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        if (! empty($validated['search'])) {
            $term = Str::lower($validated['search']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%$term%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%$term%"]);
            });
        }

        $systems = $query->cursorPaginate($perPage, ['*'], 'cursor', $validated['cursor'] ?? null);

        $items = collect($systems->items())
            ->map(fn (System $system) => (new SystemResource($system))->toArray($request))
            ->values()
            ->all();

        return $this->ok(
            ['items' => $items],
            'Systems retrieved.',
            [
                'next_cursor' => optional($systems->nextCursor())->encode(),
                'prev_cursor' => optional($systems->previousCursor())->encode(),
            ]
        );
    }

    public function store(StoreSystemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = (int) $request->user()->company_id;

        $system = System::create($data)->load(['location:id,name,code'])->loadCount('assets');
        $this->auditLogger->created($system, Arr::only($system->getAttributes(), array_keys($data)));

        return $this->ok(
            (new SystemResource($system))->toArray($request),
            'System created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, System $system): JsonResponse
    {
        $this->authorize('view', $system);

        $system->load(['location:id,name,code'])->loadCount('assets');

        return $this->ok(
            (new SystemResource($system))->toArray($request),
            'System retrieved.'
        );
    }

    public function update(UpdateSystemRequest $request, System $system): JsonResponse
    {
        $data = $request->validated();

        if ($data !== []) {
            $before = Arr::only($system->getOriginal(), array_keys($data));
            $system->fill($data);
            $system->save();
            $this->auditLogger->updated($system, $before, Arr::only($system->getAttributes(), array_keys($data)));
        }

        $system->load(['location:id,name,code'])->loadCount('assets');

        return $this->ok(
            (new SystemResource($system))->toArray($request),
            'System updated.'
        );
    }

    public function destroy(System $system): JsonResponse
    {
        $this->authorize('delete', $system);

        $before = $system->getAttributes();
        $system->delete();
        $this->auditLogger->deleted($system, $before);

        return $this->ok(null, 'System removed.');
    }
}

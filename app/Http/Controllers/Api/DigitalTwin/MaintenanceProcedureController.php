<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\StoreMaintenanceProcedureRequest;
use App\Http\Requests\DigitalTwin\UpdateMaintenanceProcedureRequest;
use App\Http\Resources\DigitalTwin\MaintenanceProcedureResource;
use App\Models\MaintenanceProcedure;
use App\Services\DigitalTwin\MaintenanceProcedureService;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaintenanceProcedureController extends ApiController
{
    private const CATEGORIES = ['preventive', 'corrective', 'inspection', 'calibration', 'safety'];

    public function __construct(
        private readonly MaintenanceProcedureService $service,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $this->authorize('viewAny', MaintenanceProcedure::class);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:191'],
        ]);
        $perPage = $this->perPage($request, 25, 100);

        $query = MaintenanceProcedure::query()
            ->where('company_id', $companyId)
            ->orderBy('code');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['search'])) {
            $term = Str::lower($validated['search']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(code) LIKE ?', ["%$term%"])
                    ->orWhereRaw('LOWER(title) LIKE ?', ["%$term%"]);
            });
        }

        $procedures = $query->cursorPaginate($perPage, ['*'], 'cursor', $validated['cursor'] ?? null);

        $items = collect($procedures->items())
            ->map(fn (MaintenanceProcedure $procedure) => (new MaintenanceProcedureResource($procedure))->toArray($request))
            ->values()
            ->all();

        return $this->ok(
            ['items' => $items],
            'Maintenance procedures retrieved.',
            [
                'next_cursor' => optional($procedures->nextCursor())->encode(),
                'prev_cursor' => optional($procedures->previousCursor())->encode(),
            ]
        );
    }

    public function store(StoreMaintenanceProcedureRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $procedure = $this->service->create($user, $companyId, $request->validated());

        return $this->ok(
            (new MaintenanceProcedureResource($procedure))->toArray($request),
            'Maintenance procedure created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, MaintenanceProcedure $procedure): JsonResponse
    {
        $this->authorize('view', $procedure);

        $procedure->load('steps');

        return $this->ok(
            (new MaintenanceProcedureResource($procedure))->toArray($request),
            'Maintenance procedure retrieved.'
        );
    }

    public function update(UpdateMaintenanceProcedureRequest $request, MaintenanceProcedure $procedure): JsonResponse
    {
        $updated = $this->service->update($procedure, $request->validated());

        return $this->ok(
            (new MaintenanceProcedureResource($updated))->toArray($request),
            'Maintenance procedure updated.'
        );
    }

    public function destroy(MaintenanceProcedure $procedure): JsonResponse
    {
        $this->authorize('delete', $procedure);

        $before = $procedure->getAttributes();
        $procedure->delete();
        $this->auditLogger->deleted($procedure, $before);

        return $this->ok(null, 'Maintenance procedure removed.');
    }
}

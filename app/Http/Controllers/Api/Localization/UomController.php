<?php

namespace App\Http\Controllers\Api\Localization;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Localization\StoreUomRequest;
use App\Http\Requests\Localization\UpdateUomRequest;
use App\Http\Resources\Localization\UomResource;
use App\Models\Uom;
use App\Services\UnitConversionService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UomController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UnitConversionService $conversionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Uom::query()->orderBy('code');

        $dimension = $request->query('dimension');

        if ($dimension !== null) {
            $query->where('dimension', $dimension);
        }

        $paginator = $query->cursorPaginate(
            $this->perPage($request, 25, 100),
            ['*'],
            'cursor',
            $request->query('cursor')
        );

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, UomResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Units retrieved.', $meta);
    }

    public function store(StoreUomRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $uom = Uom::create($payload);

        $this->auditLogger->created($uom, $uom->toArray());

        $this->conversionService->resetCache();

        return $this->ok((new UomResource($uom))->toArray($request), 'Unit created.');
    }

    public function update(UpdateUomRequest $request, Uom $uom): JsonResponse
    {
        $payload = $request->validated();
        $before = $uom->getOriginal();

        $uom->fill($payload);
        $dirty = $uom->isDirty();

        $uom->save();

        if ($dirty) {
            $this->auditLogger->updated($uom, $before, $uom->toArray());
        }

        $this->conversionService->resetCache();

        return $this->ok((new UomResource($uom))->toArray($request), 'Unit updated.');
    }

    public function destroy(Request $request, Uom $uom): JsonResponse
    {
        if ($uom->si_base) {
            return $this->fail('Cannot delete SI base units.', 422);
        }

        $relationships = $this->usageCount($uom);

        if ($relationships > 0) {
            return $this->fail('Unit is in use and cannot be deleted.', 422);
        }

        $before = $uom->toArray();
        $uom->delete();

        $this->auditLogger->deleted($uom, $before);
        $this->conversionService->resetCache();

        return $this->ok(null, 'Unit deleted.');
    }

    private function usageCount(Uom $uom): int
    {
        $partUsage = DB::table('parts')->where('base_uom_id', $uom->id)->count();
        $conversionUsage = DB::table('uom_conversions')
            ->where('from_uom_id', $uom->id)
            ->orWhere('to_uom_id', $uom->id)
            ->whereNull('deleted_at')
            ->count();

        return (int) $partUsage + (int) $conversionUsage;
    }
}

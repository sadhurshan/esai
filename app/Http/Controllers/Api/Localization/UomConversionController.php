<?php

namespace App\Http\Controllers\Api\Localization;

use App\Exceptions\UnitConversionException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Localization\ConvertRequest;
use App\Http\Requests\Localization\UpsertUomConversionRequest;
use App\Http\Resources\Localization\UomConversionResource;
use App\Models\Part;
use App\Models\Uom;
use App\Models\UomConversion;
use App\Services\UnitConversionService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UomConversionController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UnitConversionService $conversionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = UomConversion::query()->with(['from', 'to']);

        if ($request->filled('from_code')) {
            $query->whereHas('from', fn ($builder) => $builder->where('code', $request->query('from_code')));
        }

        if ($request->filled('to_code')) {
            $query->whereHas('to', fn ($builder) => $builder->where('code', $request->query('to_code')));
        }

        if ($request->filled('dimension')) {
            $dimension = $request->query('dimension');
            $query->whereHas('from', fn ($builder) => $builder->where('dimension', $dimension));
        }

        $paginator = $query
            ->orderBy('from_uom_id')
            ->orderBy('to_uom_id')
            ->cursorPaginate(
                $this->perPage($request, 25, 100),
                ['*'],
                'cursor',
                $request->query('cursor')
            );

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, UomConversionResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Conversions retrieved.', $meta);
    }

    public function upsert(UpsertUomConversionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $from = $payload['from_code'];
        $to = $payload['to_code'];

        /** @var Uom|null $fromModel */
        $fromModel = Uom::query()->where('code', $from)->first();
        /** @var Uom|null $toModel */
        $toModel = Uom::query()->where('code', $to)->first();

        if (! $fromModel || ! $toModel) {
            return $this->fail('Unknown units.', 422);
        }

        if ($fromModel->dimension !== $toModel->dimension) {
            return $this->fail('Unit dimensions must match.', 422);
        }

        $record = UomConversion::query()->firstOrNew([
            'from_uom_id' => $fromModel->id,
            'to_uom_id' => $toModel->id,
        ]);

        $before = $record->exists ? $record->getOriginal() : [];

        $record->factor = $payload['factor'];
        $record->offset = $payload['offset'] ?? 0;

        $dirty = $record->isDirty();

    // TODO: clarify with spec - enforce offset cycle checks for temperature chains.
        $record->save();

        if ($record->wasRecentlyCreated) {
            $this->auditLogger->created($record, $record->toArray());
        } elseif ($dirty) {
            $this->auditLogger->updated($record, $before, $record->toArray());
        }

        $this->conversionService->resetCache();

        $record->load(['from', 'to']);

        return $this->ok((new UomConversionResource($record))->toArray($request), 'Conversion saved.');
    }

    public function convert(ConvertRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $from = Uom::query()->where('code', $payload['from_code'])->first();
        $to = Uom::query()->where('code', $payload['to_code'])->first();

        if (! $from || ! $to) {
            return $this->fail('Unknown units.', 422);
        }

        try {
            $quantity = $this->conversionService->convert((float) $payload['qty'], $from, $to);
        } catch (UnitConversionException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        return $this->ok([
            'qty_converted' => $quantity->__toString(),
        ]);
    }

    public function convertForPart(Request $request, Part $part): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $qty = (float) $request->query('qty', 0);

        $fromCode = $request->query('from');
        $toCode = $request->query('to');

        if (! $fromCode || ! $toCode) {
            return $this->fail('Both from and to codes are required.', 422);
        }

        $from = Uom::query()->where('code', $fromCode)->first();
        $to = Uom::query()->where('code', $toCode)->first();

        if (! $from || ! $to) {
            return $this->fail('Unknown units.', 422);
        }

        if ((int) $part->company_id !== (int) $companyId) {
            return $this->fail('Part not found for this company.', 404);
        }

        try {
            $baseUom = $this->conversionService->resolvePartBaseUom($part);
            $convertedToBase = $this->conversionService->convert($qty, $from, $baseUom);
            $convertedToTarget = $this->conversionService->convert($convertedToBase, $baseUom, $to);
        } catch (UnitConversionException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        return $this->ok([
            'qty_converted' => $convertedToTarget->__toString(),
            'base_qty' => $convertedToBase->__toString(),
            'base_uom' => $part->base_uom_code ?? $part->uom,
        ]);
    }
}

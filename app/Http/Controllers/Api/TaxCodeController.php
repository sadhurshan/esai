<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTaxCodeRequest;
use App\Http\Requests\UpdateTaxCodeRequest;
use App\Http\Resources\TaxCodeResource;
use App\Models\TaxCode;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaxCodeController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxCode::class);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:191'],
            'active' => ['nullable', 'boolean'],
            'type' => ['nullable', Rule::in(['vat', 'gst', 'sales', 'withholding', 'custom'])],
        ]);

        $companyId = (int) $request->user()->company_id;
        $perPage = $this->perPage($request, 25, 100);

        $query = TaxCode::query()
            ->where('company_id', $companyId)
            ->orderBy('code');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['search'])) {
            $term = Str::lower($validated['search']);

            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereRaw('LOWER(code) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
            });
        }

        $taxCodes = $query->cursorPaginate($perPage, ['*'], 'cursor', $validated['cursor'] ?? null);

        $items = collect($taxCodes->items())
            ->map(fn (TaxCode $taxCode) => (new TaxCodeResource($taxCode))->toArray($request))
            ->values()
            ->all();

        return $this->ok(
            ['items' => $items],
            'Tax codes retrieved.',
            [
                'next_cursor' => optional($taxCodes->nextCursor())->encode(),
                'prev_cursor' => optional($taxCodes->previousCursor())->encode(),
            ]
        );
    }

    public function store(StoreTaxCodeRequest $request): JsonResponse
    {
        $this->authorize('create', TaxCode::class);

        $payload = $request->payload();
        $payload['company_id'] = (int) $request->user()->company_id;

        $taxCode = TaxCode::create($payload);

        $this->auditLogger->created($taxCode, Arr::only($taxCode->getAttributes(), array_keys($payload)));

        return $this->ok(
            (new TaxCodeResource($taxCode))->toArray($request),
            'Tax code created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, TaxCode $taxCode): JsonResponse
    {
        $this->authorize('view', $taxCode);

        return $this->ok(
            (new TaxCodeResource($taxCode))->toArray($request),
            'Tax code retrieved.'
        );
    }

    public function update(UpdateTaxCodeRequest $request, TaxCode $taxCode): JsonResponse
    {
        $this->authorize('update', $taxCode);

        $payload = $request->payload();

        if ($payload === []) {
            return $this->ok(
                (new TaxCodeResource($taxCode))->toArray($request),
                'Tax code updated.'
            );
        }

        $before = Arr::only($taxCode->getOriginal(), array_keys($payload));
        $taxCode->fill($payload);

        if ($taxCode->isDirty()) {
            $taxCode->save();
            $this->auditLogger->updated($taxCode, $before, Arr::only($taxCode->getAttributes(), array_keys($payload)));
        }

        return $this->ok(
            (new TaxCodeResource($taxCode))->toArray($request),
            'Tax code updated.'
        );
    }

    public function destroy(TaxCode $taxCode): JsonResponse
    {
        $this->authorize('delete', $taxCode);

        $before = $taxCode->getAttributes();
        $taxCode->delete();
        $this->auditLogger->deleted($taxCode, $before);

        return $this->ok(null, 'Tax code removed.');
    }
}

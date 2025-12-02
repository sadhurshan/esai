<?php

namespace App\Http\Controllers\Api\Library;

use App\Enums\DigitalTwinAssetType;
use App\Enums\DigitalTwinStatus;
use App\Enums\DigitalTwinVisibility;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Library\DigitalTwinIndexRequest;
use App\Http\Resources\DigitalTwinAssetResource;
use App\Http\Resources\DigitalTwinCategoryResource;
use App\Http\Resources\DigitalTwinLibraryListResource;
use App\Http\Resources\DigitalTwinLibraryResource;
use App\Http\Resources\DigitalTwinSpecResource;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Support\CompanyContext;
use Symfony\Component\HttpFoundation\Response;

class DigitalTwinController extends ApiController
{
    public function index(DigitalTwinIndexRequest $request): JsonResponse
    {
        return CompanyContext::bypass(function () use ($request): JsonResponse {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 20);
            $perPage = $perPage > 0 ? min($perPage, 50) : 20;

            $query = DigitalTwin::query()
                ->select('digital_twins.*')
                ->with([
                    'category',
                    'assets' => fn ($assets) => $assets->orderByDesc('is_primary')->orderBy('id'),
                ])
                ->where('status', DigitalTwinStatus::Published)
                ->where('visibility', DigitalTwinVisibility::Public);

            $query->where(function (Builder $builder): void {
                $builder->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $category): Builder => $category->where('is_active', true));
            });

            $this->applyFilters($query, $filters);

            $sort = (string) ($filters['sort'] ?? ($filters['q'] ?? null ? 'relevance' : 'updated_at'));
            $this->applySorting($query, $sort, isset($filters['q']) && is_string($filters['q']) && trim($filters['q']) !== '');

            $paginator = $query->cursorPaginate($perPage)->withQueryString();

            $payload = $this->buildIndexPayload($request, $paginator, $filters);

            return $this->ok($payload['data'], 'Digital twins retrieved.', $payload['meta']);
        });
    }

    public function show(Request $request, DigitalTwin $digitalTwin): JsonResponse
    {
        return CompanyContext::bypass(function () use ($request, $digitalTwin): JsonResponse {
            $twin = $this->guardLibraryTwin($digitalTwin);

            $twin->loadMissing([
                'category',
                'specs' => fn ($specs) => $specs->orderBy('sort_order'),
                'assets' => fn ($assets) => $assets->orderByDesc('is_primary')->orderBy('id'),
            ]);

            return $this->ok([
                'digital_twin' => DigitalTwinLibraryResource::make($twin),
            ], 'Digital twin retrieved.');
        });
    }

    public function useForRfq(Request $request, DigitalTwin $digitalTwin): JsonResponse
    {
        return CompanyContext::bypass(function () use ($request, $digitalTwin): JsonResponse {
            $twin = $this->guardLibraryTwin($digitalTwin);

            $twin->loadMissing([
                'category',
                'specs' => fn ($specs) => $specs->orderBy('sort_order'),
                'assets' => fn ($assets) => $assets->orderByDesc('is_primary')->orderBy('id'),
            ]);

            return $this->ok([
                'digital_twin' => DigitalTwinLibraryResource::make($twin),
                'draft' => $this->buildRfqDraftPayload($twin, $request),
            ], 'RFQ draft prepared from digital twin.');
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q']) && is_string($filters['q'])) {
            $this->applySearchFilter($query, $filters['q']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $tags = collect($filters['tags'] ?? []);
        if (! empty($filters['tag'])) {
            $tags->push($filters['tag']);
        }

        $tags = $tags
            ->filter(static fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->map(static fn ($tag) => trim((string) $tag))
            ->values();

        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        $assetFilters = collect($filters['has_assets'] ?? []);
        if (! empty($filters['has_asset'])) {
            $assetFilters->push($filters['has_asset']);
        }

        $assetTypes = $assetFilters
            ->map(static fn ($value) => is_string($value) ? strtoupper($value) : null)
            ->filter(fn ($value) => $value !== null)
            ->map(static fn ($value) => DigitalTwinAssetType::tryFrom($value))
            ->filter()
            ->unique()
            ->values();

        if ($assetTypes->isNotEmpty()) {
            $query->whereHas('assets', fn (Builder $assets) => $assets->whereIn('type', $assetTypes->map(fn (DigitalTwinAssetType $type) => $type->value)->all()));
        }

        if (! empty($filters['updated_from'])) {
            $from = Carbon::parse($filters['updated_from'])->startOfDay();
            $query->where('digital_twins.updated_at', '>=', $from);
        }

        if (! empty($filters['updated_to'])) {
            $to = Carbon::parse($filters['updated_to'])->endOfDay();
            $query->where('digital_twins.updated_at', '<=', $to);
        }
    }

    private function applySorting(Builder $query, string $sort, bool $hasSearch): void
    {
        $normalized = strtolower(trim($sort));

        if ($normalized === 'relevance' && $hasSearch) {
            $query->orderByDesc('relevance_score')
                ->orderByDesc('digital_twins.updated_at')
                ->orderByDesc('digital_twins.id');

            return;
        }

        if ($normalized === 'title') {
            $query->orderBy('digital_twins.title')
                ->orderBy('digital_twins.id');

            return;
        }

        $query->orderByDesc('digital_twins.updated_at')
            ->orderByDesc('digital_twins.id');
    }

    private function applySearchFilter(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $partial = '%'.$term.'%';
        $lower = '%'.mb_strtolower($term, 'UTF-8').'%';

        $query->selectRaw('
            (
                (CASE WHEN digital_twins.title LIKE ? THEN 4 ELSE 0 END) +
                (CASE WHEN digital_twins.summary LIKE ? THEN 2 ELSE 0 END) +
                (CASE WHEN digital_twins.tags_search LIKE ? THEN 1 ELSE 0 END)
            ) AS relevance_score
        ', ["$term%", $partial, $lower]);

        $query->where(function (Builder $builder) use ($partial, $lower): void {
            $builder->where('digital_twins.title', 'like', $partial)
                ->orWhere('digital_twins.summary', 'like', $partial)
                ->orWhere('digital_twins.tags_search', 'like', $lower)
                ->orWhereHas('specs', function (Builder $specs) use ($partial): void {
                    $specs->where('name', 'like', $partial)
                        ->orWhere('value', 'like', $partial);
                });
        });
    }

    /**
     * @return array{data: array<string, mixed>, meta: array{next_cursor: string|null, prev_cursor: string|null, per_page: int}}
     */
    private function buildIndexPayload(Request $request, CursorPaginator $paginator, array $filters): array
    {
        $items = DigitalTwinLibraryListResource::collection(collect($paginator->items()))->toArray($request);

        $meta = [
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'per_page' => $paginator->perPage(),
        ];

        $data = [
            'items' => $items,
            'meta' => $meta,
        ];

        $includes = collect($filters['include'] ?? []);

        if ($includes->contains('categories')) {
            $data['categories'] = DigitalTwinCategoryResource::collection($this->loadActiveCategories());
        }

        return [
            'data' => $data,
            'meta' => ['data' => $meta],
        ];
    }

    private function loadActiveCategories(): Collection
    {
        $treeLoader = function (Builder|HasMany $children) use (&$treeLoader): void {
            $children->where('is_active', true)
                ->orderBy('name')
                ->with(['children' => $treeLoader]);
        };

        return DigitalTwinCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->with(['children' => $treeLoader])
            ->get();
    }

    private function guardLibraryTwin(DigitalTwin $digitalTwin): DigitalTwin
    {
        $digitalTwin->loadMissing('category');

        $isPublished = $digitalTwin->status === DigitalTwinStatus::Published;
        $isPublic = $digitalTwin->visibility === DigitalTwinVisibility::Public;
        $categoryActive = $digitalTwin->category_id === null || ($digitalTwin->category && $digitalTwin->category->is_active);

        if (! $isPublished || ! $isPublic || ! $categoryActive) {
            abort(Response::HTTP_NOT_FOUND, 'Digital twin not found.');
        }

        return $digitalTwin;
    }

    private function buildRfqDraftPayload(DigitalTwin $twin, Request $request): array
    {
        $line = [
            'part_name' => $twin->title,
            'spec' => $this->buildSpecSummary($twin),
            'method' => null,
            'material' => null,
            'tolerance' => null,
            'finish' => null,
            'quantity' => null,
            'uom' => null,
            'target_price' => null,
            'required_date' => null,
        ];

        foreach ($twin->specs as $spec) {
            $normalized = Str::of($spec->name ?? '')->snake()->value();
            $normalizedCompact = str_replace('_', '', $normalized);
            $value = $spec->value;

            if ($value === null || trim((string) $value) === '') {
                if ($line['uom'] === null && $spec->uom) {
                    $line['uom'] = $spec->uom;
                }

                continue;
            }

            switch ($normalized) {
                case 'method':
                case 'manufacturing_method':
                    $line['method'] = $value;
                    break;
                case 'material':
                    $line['material'] = $value;
                    break;
                case 'tolerance':
                    $line['tolerance'] = $value;
                    break;
                case 'finish':
                    $line['finish'] = $value;
                    break;
                case 'quantity':
                case 'qty':
                case 'target_quantity':
                    $line['quantity'] = $this->normalizeInteger($value);
                    break;
                case 'uom':
                case 'uo_m':
                case 'u_o_m':
                case 'unit':
                case 'unit_of_measure':
                case 'unit_of_measurement':
                    $line['uom'] = $spec->uom ?: $value;
                    break;
                case 'target_price':
                case 'price':
                    $line['target_price'] = $this->normalizeFloat($value);
                    break;
            }

            if (
                $line['uom'] === null
                && $spec->uom
                && in_array($normalizedCompact, ['uom', 'unit', 'unitofmeasure', 'unitofmeasurement', 'quantity', 'qty', 'targetquantity'], true)
            ) {
                $line['uom'] = $spec->uom;
            }
        }

        return [
            'source' => 'digital_twin',
            'digital_twin_id' => $twin->id,
            'title' => $twin->title,
            'summary' => $twin->summary,
            'notes' => $twin->revision_notes,
            'lines' => [$line],
            'specs' => DigitalTwinSpecResource::collection($twin->specs)->toArray($request),
            'attachments' => DigitalTwinAssetResource::collection($twin->assets)->toArray($request),
        ];
    }

    private function buildSpecSummary(DigitalTwin $twin): ?string
    {
        $lines = $twin->specs
            ->map(function ($spec): ?string {
                $value = trim((string) ($spec->value ?? ''));

                if ($value === '') {
                    return null;
                }

                $uom = $spec->uom ? ' '.$spec->uom : '';

                return trim($spec->name.': '.$value.$uom);
            })
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode("\n");
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $filtered = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        if ($filtered === false || $filtered === '') {
            return null;
        }

        return (int) round((float) $filtered);
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $filtered = filter_var((string) $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        return $filtered === false || $filtered === '' ? null : (float) $filtered;
    }
}

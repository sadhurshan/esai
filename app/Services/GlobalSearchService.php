<?php

namespace App\Services;

use App\Enums\SearchEntityType;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GlobalSearchService
{
    private const MAX_RESULTS_PER_ENTITY = 50;

    /**
     * @param array<string> $entityTypes
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function search(array $entityTypes, string $query, array $filters, Company $company, int $page = 1, int $perPage = 20): array
    {
        $tokens = $this->tokenize($query);

        if ($tokens === []) {
            return $this->emptyResult($page, $perPage);
        }

        $entityEnums = $this->normalizeEntityTypes($entityTypes);

        if ($entityEnums === []) {
            $entityEnums = SearchEntityType::cases();
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        $this->logSearch($company, $entityEnums, $tokens, $filters, $page, $perPage);

        $results = collect();

        foreach ($entityEnums as $entityType) {
            $results = $results->merge($this->searchByType($entityType, $tokens, $filters, $company));
        }

        if ($results->isEmpty()) {
            return $this->emptyResult($page, $perPage);
        }

        $sorted = $results
            ->sortByDesc(fn (array $item) => [$item['score'], $item['created_at'] ?? ''])
            ->values();

        $total = $sorted->count();
        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $items = $sorted
            ->slice($offset, $perPage)
            ->map(function (array $item): array {
                unset($item['score']);

                return $item;
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function getAllowedEntityTypesForUser(User $user): array
    {
        // Future feature-gating can filter entity types per plan or role.
        return SearchEntityType::values();
    }

    /**
     * @param array<int, string> $tokens
     * @param array<string, mixed> $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function searchByType(SearchEntityType $entityType, array $tokens, array $filters, Company $company): Collection
    {
        return match ($entityType) {
            SearchEntityType::Supplier => $this->searchSuppliers($tokens, $filters, $company),
            SearchEntityType::Part => $this->searchParts($tokens, $filters, $company),
            SearchEntityType::RFQ => $this->searchRfqs($tokens, $filters, $company),
            SearchEntityType::PurchaseOrder => $this->searchPurchaseOrders($tokens, $filters, $company),
            SearchEntityType::Invoice => $this->searchInvoices($tokens, $filters, $company),
            SearchEntityType::Document => $this->searchDocuments($tokens, $filters, $company),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchSuppliers(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);
        $capabilityColumn = Schema::hasColumn('suppliers', 'capabilities_search')
            ? 'suppliers.capabilities_search'
            : 'suppliers.capabilities';

        $builder = Supplier::query()
            ->select([
                'suppliers.id',
                'suppliers.name',
                'suppliers.status',
                'suppliers.capabilities',
                'suppliers.city',
                'suppliers.country',
                'suppliers.created_at',
                'suppliers.email',
                'suppliers.phone',
            ])
            ->where('company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['suppliers.name', $capabilityColumn], $booleanQuery, $tokens);

        $statuses = $this->extractArrayFilter($filters, 'status');
        if ($statuses !== []) {
            $builder->whereIn('status', $statuses);
        }

        $tags = $this->extractArrayFilter($filters, 'tags');
        if ($tags !== []) {
            $builder->where(function ($query) use ($tags, $capabilityColumn): void {
                foreach ($tags as $tag) {
                    $query->where($capabilityColumn, 'like', '%'.$tag.'%');
                }
            });
        }

        return $builder->get()->map(function (Supplier $supplier) use ($relevanceColumn): array {
            $location = trim(implode(', ', array_filter([$supplier->city, $supplier->country])));

            return [
                'type' => SearchEntityType::Supplier->value,
                'id' => $supplier->id,
                'title' => $supplier->name,
                'identifier' => $supplier->name,
                'status' => $supplier->status,
                'created_at' => optional($supplier->created_at)->toAtomString(),
                'snippet' => Str::limit($this->supplierCapabilitiesText($supplier), 160),
                'additional' => array_filter([
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'location' => $location !== '' ? $location : null,
                ]),
                'score' => $this->resolveScore($supplier, $relevanceColumn),
            ];
        });
    }

    private function supplierCapabilitiesText(Supplier $supplier): string
    {
        $capabilitiesSearch = $supplier->getAttribute('capabilities_search');

        if (is_string($capabilitiesSearch) && $capabilitiesSearch !== '') {
            return $capabilitiesSearch;
        }

        $capabilities = $supplier->capabilities;

        if (is_array($capabilities)) {
            return collect($capabilities)
                ->flatten()
                ->filter(static fn ($value): bool => is_string($value) && $value !== '')
                ->unique()
                ->take(10)
                ->implode(', ');
        }

        if (is_string($capabilities) && $capabilities !== '') {
            return $capabilities;
        }

        return '';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchParts(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);

        $builder = Part::query()
            ->select([
                'parts.id',
                'parts.part_number',
                'parts.name',
                'parts.spec',
                'parts.created_at',
            ])
            ->where('parts.company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['parts.part_number', 'parts.name', 'parts.spec'], $booleanQuery, $tokens);

        $this->applyDateFilter($builder, $filters, 'parts.created_at');

        $tags = $this->extractArrayFilter($filters, 'tags');
        if ($tags !== []) {
            $normalizedTags = array_values(array_unique(array_map(static fn (string $tag): string => Str::lower($tag), $tags)));

            $builder->whereExists(function ($query) use ($normalizedTags, $company): void {
                $query->selectRaw('1')
                    ->from('part_tags')
                    ->whereColumn('part_tags.part_id', 'parts.id')
                    ->where('part_tags.company_id', $company->id)
                    ->whereNull('part_tags.deleted_at')
                    ->whereIn('part_tags.normalized_tag', $normalizedTags)
                    ->groupBy('part_tags.part_id')
                    ->havingRaw('COUNT(DISTINCT part_tags.normalized_tag) >= ?', [count($normalizedTags)]);
            });
        }

        /** @var EloquentCollection<int, Part> $items */
        $items = $builder->with('tags')->get();

        return $items->map(function (Part $part) use ($relevanceColumn): array {
            $tags = $part->tags->pluck('tag')->filter()->values()->all();
            $title = $part->part_number ?: $part->name;
            $identifier = $part->part_number ?: $part->name;

            return [
                'type' => SearchEntityType::Part->value,
                'id' => $part->id,
                'title' => $title,
                'identifier' => $identifier,
                'status' => null,
                'created_at' => optional($part->created_at)->toAtomString(),
                'snippet' => Str::limit((string) ($part->spec ?? ''), 160),
                'additional' => array_filter([
                    'part_number' => $part->part_number,
                    'tags' => $tags,
                ]),
                'score' => $this->resolveScore($part, $relevanceColumn),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchRfqs(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);
        $builder = RFQ::query()
            ->select(['id', 'title', 'number', 'status', 'publish_at', 'created_at'])
            ->where('company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['title', 'number'], $booleanQuery, $tokens);

        $statuses = $this->extractArrayFilter($filters, 'status');
        if ($statuses !== []) {
            $builder->whereIn('status', $statuses);
        }

        $this->applyDateFilter($builder, $filters, 'publish_at');

        return $builder->get()->map(function (RFQ $rfq) use ($relevanceColumn): array {
            return [
                'type' => SearchEntityType::RFQ->value,
                'id' => $rfq->id,
                'title' => $rfq->title,
                'identifier' => $rfq->number,
                'status' => $rfq->status,
                'created_at' => optional($rfq->publish_at ?? $rfq->created_at)->toAtomString(),
                'snippet' => null,
                'additional' => [],
                'score' => $this->resolveScore($rfq, $relevanceColumn),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchPurchaseOrders(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);
        $builder = PurchaseOrder::query()
            ->select(['id', 'po_number', 'status', 'created_at'])
            ->where('company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['po_number'], $booleanQuery, $tokens);

        $statuses = $this->extractArrayFilter($filters, 'status');
        if ($statuses !== []) {
            $builder->whereIn('status', $statuses);
        }

        $this->applyDateFilter($builder, $filters, 'created_at');

        return $builder->get()->map(function (PurchaseOrder $purchaseOrder) use ($relevanceColumn): array {
            return [
                'type' => SearchEntityType::PurchaseOrder->value,
                'id' => $purchaseOrder->id,
                'title' => 'Purchase Order',
                'identifier' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'created_at' => optional($purchaseOrder->created_at)->toAtomString(),
                'snippet' => null,
                'additional' => [],
                'score' => $this->resolveScore($purchaseOrder, $relevanceColumn),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchInvoices(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);
        $builder = Invoice::query()
            ->select(['id', 'invoice_number', 'status', 'created_at', 'total'])
            ->where('company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['invoice_number'], $booleanQuery, $tokens);

        $statuses = $this->extractArrayFilter($filters, 'status');
        if ($statuses !== []) {
            $builder->whereIn('status', $statuses);
        }

        $this->applyDateFilter($builder, $filters, 'created_at');

        return $builder->get()->map(function (Invoice $invoice) use ($relevanceColumn): array {
            return [
                'type' => SearchEntityType::Invoice->value,
                'id' => $invoice->id,
                'title' => 'Invoice',
                'identifier' => $invoice->invoice_number,
                'status' => $invoice->status,
                'created_at' => optional($invoice->created_at)->toAtomString(),
                'snippet' => null,
                'additional' => array_filter([
                    'total' => $invoice->total,
                ]),
                'score' => $this->resolveScore($invoice, $relevanceColumn),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchDocuments(array $tokens, array $filters, Company $company): Collection
    {
        $booleanQuery = $this->booleanQuery($tokens);
        $builder = Document::query()
            ->select(['id', 'filename', 'category', 'visibility', 'created_at', 'meta'])
            ->where('company_id', $company->id)
            ->limit(self::MAX_RESULTS_PER_ENTITY);

        $relevanceColumn = $this->applySearchCondition($builder, ['filename'], $booleanQuery, $tokens);

        if ($category = $this->extractScalarFilter($filters, 'category')) {
            $builder->where('category', $category);
        }

        if ($visibility = $this->extractScalarFilter($filters, 'visibility')) {
            $builder->where('visibility', $visibility);
        }

        $this->applyDateFilter($builder, $filters, 'created_at');

        return $builder->get()->map(function (Document $document) use ($relevanceColumn): array {
            $meta = is_array($document->meta) ? $document->meta : [];
            $description = isset($meta['description']) && is_string($meta['description'])
                ? $meta['description']
                : '';

            return [
                'type' => SearchEntityType::Document->value,
                'id' => $document->id,
                'title' => $document->filename,
                'identifier' => $document->filename,
                'status' => $document->visibility,
                'created_at' => optional($document->created_at)->toAtomString(),
                'snippet' => $description !== '' ? Str::limit($description, 160) : null,
                'additional' => [
                    'category' => $document->category,
                    'visibility' => $document->visibility,
                ],
                'score' => $this->resolveScore($document, $relevanceColumn),
            ];
        });
    }

    private function applyDateFilter(EloquentBuilder|QueryBuilder $builder, array $filters, string $column): void
    {
        $from = $this->extractDateFilter($filters, 'date_from');
        $to = $this->extractDateFilter($filters, 'date_to');

        if ($from !== null) {
            $builder->whereDate($column, '>=', $from);
        }

        if ($to !== null) {
            $builder->whereDate($column, '<=', $to);
        }
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $query): array
    {
        $clean = trim($query);

        if ($clean === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $clean) ?: [];

        return array_values(array_filter(array_map(static function (string $token): string {
            $normalised = trim($token, "\"'`~!@#$%^&*()_+-={}|[]\\:;<>?,./");

            return Str::lower($normalised);
        }, $tokens), static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<SearchEntityType> $entityTypes
     * @param array<int, string> $tokens
     * @param array<string, mixed> $filters
     */
    private function logSearch(Company $company, array $entityTypes, array $tokens, array $filters, int $page, int $perPage): void
    {
        Log::info('global_search.performed', [
            'company_id' => $company->id,
            'entity_types' => array_map(static fn (SearchEntityType $type) => $type->value, $entityTypes),
            'tokens' => $tokens,
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @param array<string> $entityTypes
     * @return list<SearchEntityType>
     */
    private function normalizeEntityTypes(array $entityTypes): array
    {
        $types = [];

        foreach ($entityTypes as $type) {
            if (is_string($type)) {
                $resolved = SearchEntityType::tryFrom(Str::lower($type));

                if ($resolved instanceof SearchEntityType) {
                    $types[$resolved->value] = $resolved;
                }
            } elseif ($type instanceof SearchEntityType) {
                $types[$type->value] = $type;
            }
        }

        return array_values($types);
    }

    private function booleanQuery(array $tokens): string
    {
        return implode(' ', array_map(static fn (string $token): string => '+'.$token.'*', $tokens));
    }

    private function emptyResult(int $page, int $perPage): array
    {
        return [
            'items' => [],
            'meta' => [
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => max(1, $page),
                'last_page' => 1,
            ],
        ];
    }

    private function extractScalarFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractArrayFilter(array $filters, string $key): array
    {
        $value = $filters[$key] ?? null;

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (is_array($value)) {
            $normalised = array_map(static function ($item): ?string {
                if (! is_string($item)) {
                    return null;
                }

                $trimmed = trim($item);

                return $trimmed === '' ? null : $trimmed;
            }, $value);

            $result = array_values(array_filter($normalised, static fn (?string $item): bool => $item !== null));

            /** @var list<string> $result */
            return $result;
        }

        return [];
    }

    private function extractDateFilter(array $filters, string $key): ?Carbon
    {
        $value = $filters[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param EloquentBuilder|QueryBuilder $builder
     * @param list<string> $columns
     * @param list<string> $tokens
     */
    private function applySearchCondition(EloquentBuilder|QueryBuilder $builder, array $columns, string $booleanQuery, array $tokens): string
    {
        if ($this->supportsFullText()) {
            $columnList = implode(', ', $columns);
            $alias = 'relevance_'.md5(implode('-', $columns));

            $builder->selectRaw("MATCH({$columnList}) AGAINST (? IN BOOLEAN MODE) AS {$alias}", [$booleanQuery])
                ->whereRaw("MATCH({$columnList}) AGAINST (? IN BOOLEAN MODE)", [$booleanQuery])
                ->orderByDesc($alias);

            return $alias;
        }

        foreach ($tokens as $token) {
            $builder->where(function ($query) use ($columns, $token): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$token.'%');
                }
            });
        }

        return 'fallback_relevance';
    }

    private function resolveScore(object $model, string $relevanceColumn): float
    {
        if ($relevanceColumn === 'fallback_relevance') {
            return 1.0;
        }

        $score = $model->{$relevanceColumn} ?? null;

        if ($score instanceof Expression) {
            $score = (float) $score->getValue(DB::connection()->getQueryGrammar());
        }

        return is_numeric($score) ? (float) $score : 1.0;
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanySupplierStatus;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\CostBandEstimator;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends ApiController
{
    public function index(Request $request, CostBandEstimator $costBandEstimator): JsonResponse
    {
        try {
            return CompanyContext::bypass(function () use ($request, $costBandEstimator): JsonResponse {
                $query = Supplier::query()
                    ->with(['company.profile'])
                    ->withCount($this->certificateCountAggregates())
                    ->select('suppliers.*')
                    ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                    ->where('companies.supplier_status', CompanySupplierStatus::Approved->value)
                    ->where('companies.directory_visibility', 'public')
                    ->whereNotNull('companies.supplier_profile_completed_at');

                if ($search = $request->query('q')) {
                    $query->where('suppliers.name', 'like', "%{$search}%");
                }

                if ($capability = $request->query('capability')) {
                    $query->whereJsonContains('suppliers.capabilities->methods', $capability);
                }

                if ($material = $request->query('material')) {
                    $query->whereJsonContains('suppliers.capabilities->materials', $material);
                }

                $industries = $this->normalizeArrayQuery($request->query('industries') ?? $request->query('industry'));

                if ($industries) {
                    $query->where(function ($industryQuery) use ($industries): void {
                        foreach ($industries as $industry) {
                            $industryQuery->orWhereJsonContains('suppliers.capabilities->industries', $industry);
                        }
                    });
                }

                if ($finish = $request->query('finish')) {
                    $query->whereJsonContains('suppliers.capabilities->finishes', $finish);
                }

                if ($tolerance = $request->query('tolerance')) {
                    $query->whereJsonContains('suppliers.capabilities->tolerances', $tolerance);
                }

                if ($location = $request->query('location')) {
                    $query->where(function ($locationQuery) use ($location): void {
                        $locationQuery
                            ->where('suppliers.country', $location)
                            ->orWhere('suppliers.city', 'like', "%{$location}%");
                    });
                }

                $certifications = $this->normalizeArrayQuery($request->query('certs') ?? $request->query('cert'));

                if ($certifications) {
                    $query->whereHas('documents', function ($documentQuery) use ($certifications): void {
                        $documentQuery
                            ->whereIn('type', $certifications)
                            ->where('status', 'valid');
                    });
                }

                if ($ratingMin = $request->query('rating_min')) {
                    $query->where('suppliers.rating_avg', '>=', (float) $ratingMin);
                }

                if ($leadTimeMax = $request->query('lead_time_max')) {
                    $query->where(function ($leadTimeQuery) use ($leadTimeMax): void {
                        $leadTimeQuery
                            ->whereNull('suppliers.lead_time_days')
                            ->orWhere('suppliers.lead_time_days', '<=', (int) $leadTimeMax);
                    });
                }

                $sort = $request->query('sort', 'match_score');
                $originLat = $this->floatQuery($request, 'origin_lat');
                $originLng = $this->floatQuery($request, 'origin_lng');

                switch ($sort) {
                    case 'rating':
                        $query->orderByDesc('suppliers.rating_avg');
                        break;
                    case 'lead_time':
                        $query->orderByRaw('CASE WHEN suppliers.lead_time_days IS NULL THEN 1 ELSE 0 END');
                        $query->orderBy('suppliers.lead_time_days');
                        break;
                    case 'distance':
                        if (! $this->applyDistanceSort($query, $originLat, $originLng)) {
                            $this->applyMatchScoreSort($query);
                        }
                        break;
                    case 'price_band':
                        $this->applyPriceBandSort($query);
                        break;
                    case 'match_score':
                    default:
                        $this->applyMatchScoreSort($query);
                        break;
                }

                $query->orderBy('suppliers.id');

                $paginator = $query->cursorPaginate($this->perPage($request));

                ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, SupplierResource::class);

                $user = $this->resolveRequestUser($request);
                $companyId = $user ? $this->resolveUserCompanyId($user) : null;

                if ($companyId !== null) {
                    $estimate = $costBandEstimator->estimateForFilters([
                        'capability' => $request->query('capability'),
                        'material' => $request->query('material'),
                        'finish' => $request->query('finish'),
                        'location' => $request->query('location'),
                    ], $companyId);

                    if ($estimate !== null) {
                        $meta['data']['cost_band_estimate'] = $estimate;
                    }
                }

                return $this->ok([
                    'items' => $items,
                ], null, $meta);
            });
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $supplierId, Request $request): JsonResponse
    {
        try {
            return CompanyContext::bypass(function () use ($supplierId, $request): JsonResponse {
                $supplier = Supplier::query()
                    ->with([
                        'company.profile',
                        'documents' => function ($documentQuery): void {
                            $documentQuery->orderBy('expires_at')->orderBy('type');
                        },
                    ])
                    ->withCount($this->certificateCountAggregates())
                    ->whereKey($supplierId)
                    ->whereHas('company', function ($companyQuery): void {
                        $companyQuery
                            ->where('supplier_status', CompanySupplierStatus::Approved->value)
                            ->where('directory_visibility', 'public')
                            ->whereNotNull('supplier_profile_completed_at');
                    })
                    ->first();

                if (! $supplier) {
                    return $this->fail('Not found', 404);
                }

                return $this->ok((new SupplierResource($supplier))->toArray($request));
            });
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    /**
     * @return array<string, callable>
     */
    private function certificateCountAggregates(): array
    {
        return [
            'documents as valid_certificates_count' => static fn (Builder $query): Builder => $query->where('status', 'valid'),
            'documents as expiring_certificates_count' => static fn (Builder $query): Builder => $query->where('status', 'expiring'),
            'documents as expired_certificates_count' => static fn (Builder $query): Builder => $query->where('status', 'expired'),
        ];
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return array<int, string>
     */
    private function normalizeArrayQuery(array|string|null $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', $value);

        return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $items))); // normalize query arrays
    }

    private function applyMatchScoreSort(Builder $query): void
    {
        $certificateScoreSql = "(SELECT COUNT(*) FROM supplier_documents WHERE supplier_documents.supplier_id = suppliers.id AND supplier_documents.status = 'valid')";
        $leadTimeDenominator = $this->clampMinimum('suppliers.lead_time_days', 1);
        $leadTimeScoreSql = sprintf(
            'CASE WHEN suppliers.lead_time_days IS NULL THEN 0 ELSE %s END',
            $this->clampMaximum("45.0 / {$leadTimeDenominator}", 1)
        );
        $matchScoreSql = sprintf(
            '((COALESCE(suppliers.rating_avg, 0) / 5) * 0.5) + (%s * 0.2) + ((CASE WHEN suppliers.verified_at IS NOT NULL THEN 1 ELSE 0 END) * 0.15) + ((CASE WHEN companies.is_verified = 1 THEN 1 ELSE 0 END) * 0.1) + (%s * 0.05)',
            $leadTimeScoreSql,
            $this->clampMaximum("({$certificateScoreSql} / 5)", 1)
        );

        $query->selectRaw($matchScoreSql.' as match_sort_score');
        $query->orderByDesc('match_sort_score');
    }

    private function applyDistanceSort(Builder $query, ?float $originLat, ?float $originLng): bool
    {
        if ($originLat === null || $originLng === null) {
            return false;
        }

        if ($this->isSqliteConnection()) {
            $expression = '(ABS(suppliers.geo_lat - ?) + ABS(suppliers.geo_lng - ?))';
            $query->selectRaw($expression.' as distance_km', [$originLat, $originLng]);
        } else {
            $earthRadiusKm = 6371;
            $haversine = sprintf(
                '%1$d * 2 * ASIN(SQRT(POWER(SIN(RADIANS((? - suppliers.geo_lat) / 2)), 2) + COS(RADIANS(suppliers.geo_lat)) * COS(RADIANS(?)) * POWER(SIN(RADIANS((? - suppliers.geo_lng) / 2)), 2)))',
                $earthRadiusKm
            );

            $query->selectRaw($haversine.' as distance_km', [$originLat, $originLat, $originLng]);
        }

        $query->orderByRaw('CASE WHEN distance_km IS NULL THEN 1 ELSE 0 END');
        $query->orderBy('distance_km');

        return true;
    }

    private function applyPriceBandSort(Builder $query): void
    {
        $expression = $this->priceBandExpression();
        $lowered = "LOWER(COALESCE({$expression}, ''))";

        $rankCase = <<<SQL
CASE
    WHEN {$lowered} IN ('budget', 'low', 'tier_1', 'tier1') THEN 1
    WHEN {$lowered} IN ('standard', 'mid', 'tier_2', 'tier2') THEN 2
    WHEN {$lowered} IN ('premium', 'high', 'tier_3', 'tier3') THEN 3
    ELSE CASE
        WHEN suppliers.moq IS NOT NULL AND suppliers.moq <= 50 THEN 1
        WHEN suppliers.moq IS NOT NULL AND suppliers.moq <= 200 THEN 2
        WHEN suppliers.moq IS NOT NULL THEN 3
        ELSE 4
    END
END
SQL;

        $query->selectRaw($rankCase.' as price_band_rank');
        $query->orderBy('price_band_rank');
    }

    private function priceBandExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "NULLIF(NULLIF(TRIM((suppliers.capabilities->>'price_band')), ''), 'null')",
            'sqlite' => "NULLIF(json_extract(suppliers.capabilities, '$.price_band'), '')",
            default => "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(suppliers.capabilities, '$.price_band')), '')",
        };
    }

    private function floatQuery(Request $request, string $key): ?float
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function clampMaximum(string $value, float $max): string
    {
        return sprintf('CASE WHEN (%s) > %F THEN %F ELSE (%s) END', $value, $max, $max, $value);
    }

    private function clampMinimum(string $value, float $min): string
    {
        return sprintf('CASE WHEN (%s) < %F THEN %F ELSE (%s) END', $value, $min, $min, $value);
    }

    private function isSqliteConnection(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
}

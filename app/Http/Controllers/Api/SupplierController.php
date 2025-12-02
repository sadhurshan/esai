<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanySupplierStatus;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            return CompanyContext::bypass(function () use ($request): JsonResponse {
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

                $direction = 'desc';

                switch ($sort) {
                    case 'rating':
                        $query->orderByDesc('suppliers.rating_avg');
                        $direction = 'desc';
                        break;
                    case 'lead_time':
                        $query->orderBy('suppliers.lead_time_days');
                        $direction = 'asc';
                        break;
                    case 'distance':
                    case 'price_band':
                    case 'match_score':
                    default:
                        // TODO: clarify distance and price band scoring strategy with spec owners.
                        $query->orderByDesc('suppliers.rating_avg');
                        $direction = 'desc';
                        break;
                }

                $query->orderBy('suppliers.id', $direction);

                $paginator = $query->cursorPaginate($this->perPage($request));

                ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, SupplierResource::class);

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
}

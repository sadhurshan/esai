<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanySupplierStatus;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Supplier::query()
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

            if ($industry = $request->query('industry')) {
                $query->whereJsonContains('suppliers.capabilities->industries', $industry);
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

            if ($certification = $request->query('cert')) {
                $query->whereHas('documents', function ($documentQuery) use ($certification): void {
                    $documentQuery->where('type', $certification)->where('status', 'valid');
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

            switch ($sort) {
                case 'rating':
                    $query->orderByDesc('suppliers.rating_avg');
                    break;
                case 'lead_time':
                    $query->orderBy('suppliers.lead_time_days');
                    break;
                case 'distance':
                case 'price_band':
                case 'match_score':
                default:
                    // TODO: clarify distance and price band scoring strategy with spec owners.
                    $query->orderByDesc('suppliers.rating_avg');
                    break;
            }

            $paginator = $query->paginate($this->perPage($request))->withQueryString();

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, SupplierResource::class);

            return $this->ok([
                'items' => $items,
                'meta' => $meta,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $supplierId, Request $request): JsonResponse
    {
        try {
            $supplier = Supplier::query()
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
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }
}

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

            if ($method = $request->query('method')) {
                $query->whereJsonContains('capabilities', $method);
            }

            if ($material = $request->query('material')) {
                $query->whereJsonContains('materials', $material);
            }

            if ($region = $request->query('region')) {
                $query->where('suppliers.location_region', $region);
            }

            $sort = $request->query('sort', 'rating');
            $allowedSorts = ['rating', 'avg_response_hours'];
            if (! in_array($sort, $allowedSorts, true)) {
                $sort = 'rating';
            }

            $query->orderBy('suppliers.'.$sort, 'desc');

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

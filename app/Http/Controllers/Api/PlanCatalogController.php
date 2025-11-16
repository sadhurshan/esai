<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PlanCatalogResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanCatalogController extends ApiController
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->orderByRaw('CASE WHEN price_usd = 0 THEN 0 WHEN price_usd IS NULL THEN 2 ELSE 1 END')
            ->orderBy('price_usd')
            ->orderBy('rfqs_per_month')
            ->get();

        return $this->ok([
            'items' => PlanCatalogResource::collection($plans),
        ], 'Plans retrieved.');
    }
}

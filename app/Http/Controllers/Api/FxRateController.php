<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpsertFxRatesRequest;
use App\Http\Resources\FxRateResource;
use App\Models\FxRate;
use App\Services\FxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FxRateController extends ApiController
{
    public function __construct(private readonly FxService $fxService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! in_array($user->role, ['owner', 'buyer_admin'], true)) {
            return $this->fail('Forbidden.', 403);
        }

        $query = FxRate::query();

        if ($base = $request->query('base_code')) {
            $query->where('base_code', strtoupper($base));
        }

        if ($quote = $request->query('quote_code')) {
            $query->where('quote_code', strtoupper($quote));
        }

        if ($asOf = $request->query('as_of')) {
            $query->whereDate('as_of', $asOf);
        }

        $paginator = $query
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 15))
            ->withQueryString();

        $result = $this->paginate($paginator, $request, FxRateResource::class);

        return $this->ok($result);
    }

    public function upsert(UpsertFxRatesRequest $request): JsonResponse
    {
        $rows = $request->payload();

        $records = $this->fxService->upsertDailyRates($rows);

        $resources = collect($records)
            ->map(fn (FxRate $rate) => (new FxRateResource($rate))->toArray($request))
            ->all();

        return $this->ok(['items' => $resources], 'FX rates updated.');
    }
}

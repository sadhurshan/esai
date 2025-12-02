<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpsertFxRatesRequest;
use App\Http\Resources\FxRateResource;
use App\Models\FxRate;
use App\Models\User;
use App\Services\FxService;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FxRateController extends ApiController
{
    private const READ_PERMISSIONS = ['billing.write'];
    private const WRITE_PERMISSIONS = ['billing.write'];

    public function __construct(
        private readonly FxService $fxService,
        private readonly PermissionRegistry $permissions,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canRead($user)) {
            return $this->fail('Billing permissions required.', 403);
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
            ->cursorPaginate($this->perPage($request, 15));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, FxRateResource::class);

        return $this->ok(['items' => $items], null, $meta);
    }

    public function upsert(UpsertFxRatesRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null || ! $this->canMutate($user)) {
            return $this->fail('Billing permissions required.', 403);
        }

        $rows = $request->payload();

        $records = $this->fxService->upsertDailyRates($rows);

        $resources = collect($records)
            ->map(fn (FxRate $rate) => (new FxRateResource($rate))->toArray($request))
            ->all();

        return $this->ok(['items' => $resources], 'FX rates updated.');
    }

    private function canRead(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $this->resolveUserCompanyId($user);

        return $this->permissions->userHasAny($user, self::READ_PERMISSIONS, $companyId);
    }

    private function canMutate(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $this->resolveUserCompanyId($user);

        return $this->permissions->userHasAny($user, self::WRITE_PERMISSIONS, $companyId);
    }
}

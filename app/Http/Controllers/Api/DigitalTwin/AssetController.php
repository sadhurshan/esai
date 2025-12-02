<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\SetAssetStatusRequest;
use App\Http\Requests\DigitalTwin\StoreAssetRequest;
use App\Http\Requests\DigitalTwin\UpdateAssetRequest;
use App\Http\Resources\DigitalTwin\AssetResource;
use App\Models\Asset;
use App\Services\DigitalTwin\AssetService;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssetController extends ApiController
{
    private const STATUSES = ['active', 'standby', 'retired', 'maintenance'];

    public function __construct(
        private readonly AssetService $assetService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $this->authorize('viewAny', Asset::class);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'location_id' => ['nullable', 'integer'],
            'system_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:191'],
        ]);
        $perPage = $this->perPage($request, 25, 100);

        $query = Asset::query()
            ->where('company_id', $companyId)
            ->with(['location:id,name,code', 'system:id,name,code'])
            ->withCount('bomItems')
            ->orderByDesc('created_at');

        if (! empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        if (! empty($validated['system_id'])) {
            $query->where('system_id', $validated['system_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $term = Str::lower($validated['search']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%$term%"])
                    ->orWhereRaw('LOWER(tag) LIKE ?', ["%$term%"])
                    ->orWhereRaw('LOWER(serial_no) LIKE ?', ["%$term%"]);
            });
        }

        $assets = $query->cursorPaginate($perPage, ['*'], 'cursor', $validated['cursor'] ?? null);
        $collection = $this->paginate($assets, $request, AssetResource::class);

        return $this->ok($collection, 'Assets retrieved.');
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $data = $request->validated();
        $data['company_id'] = $companyId;

        $asset = $this->assetService->create($user, $data);
        $asset->load(['location:id,name,code', 'system:id,name,code'])->loadCount('bomItems');

        return $this->ok(
            (new AssetResource($asset))->toArray($request),
            'Asset created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $asset->load(['location:id,name,code', 'system:id,name,code', 'documents', 'procedureLinks.procedure'])->loadCount('bomItems');

        return $this->ok(
            (new AssetResource($asset))->toArray($request),
            'Asset retrieved.'
        );
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $updated = $this->assetService->update($asset, $request->user(), $request->validated());
        $updated->load(['location:id,name,code', 'system:id,name,code', 'documents', 'procedureLinks.procedure'])->loadCount('bomItems');

        return $this->ok(
            (new AssetResource($updated))->toArray($request),
            'Asset updated.'
        );
    }

    public function setStatus(SetAssetStatusRequest $request, Asset $asset): JsonResponse
    {
        $payload = $request->validated();
        $updated = $this->assetService->setStatus($asset, $payload['status']);

        return $this->ok(
            (new AssetResource($updated->load(['location:id,name,code', 'system:id,name,code', 'procedureLinks.procedure'])))->toArray($request),
            'Asset status updated.'
        );
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        $before = $asset->getAttributes();
        $asset->delete();
        $this->auditLogger->deleted($asset, $before);

        return $this->ok(null, 'Asset removed.');
    }
}

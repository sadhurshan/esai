<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\LinkAssetProcedureRequest;
use App\Http\Requests\DigitalTwin\RecordMaintenanceCompletionRequest;
use App\Http\Resources\DigitalTwin\AssetProcedureLinkResource;
use App\Models\Asset;
use App\Models\MaintenanceProcedure;
use App\Services\DigitalTwin\MaintenancePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AssetMaintenanceController extends ApiController
{
    public function __construct(private readonly MaintenancePlanner $planner)
    {
    }

    public function link(LinkAssetProcedureRequest $request, Asset $asset, MaintenanceProcedure $procedure): JsonResponse
    {
        $this->authorize('update', $asset);

        if ((int) $asset->company_id !== (int) $procedure->company_id) {
            return $this->fail('Maintenance procedure not accessible for this asset.', 403);
        }

        $link = $this->planner->linkProcedure($asset, $procedure, $request->validated())->load('procedure');

        return $this->ok(
            (new AssetProcedureLinkResource($link))->toArray($request),
            'Maintenance procedure linked.'
        );
    }

    public function detach(Asset $asset, MaintenanceProcedure $procedure): JsonResponse
    {
        $this->authorize('update', $asset);

        if ((int) $asset->company_id !== (int) $procedure->company_id) {
            return $this->fail('Maintenance procedure not accessible for this asset.', 403);
        }

        $this->planner->detachProcedure($asset, $procedure);

        return $this->ok(null, 'Maintenance procedure detached.');
    }

    public function complete(RecordMaintenanceCompletionRequest $request, Asset $asset, MaintenanceProcedure $procedure): JsonResponse
    {
        $this->authorize('update', $asset);

        if ((int) $asset->company_id !== (int) $procedure->company_id) {
            return $this->fail('Maintenance procedure not accessible for this asset.', 403);
        }

        $payload = $request->validated();
        $completedAt = Carbon::parse($payload['completed_at']);
        $link = $this->planner->recordCompletion($asset, $procedure, $completedAt)->load('procedure');

        return $this->ok(
            (new AssetProcedureLinkResource($link))->toArray($request),
            'Maintenance completion recorded.'
        );
    }
}

<?php

namespace App\Http\Controllers\Api\DigitalTwin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DigitalTwin\SyncAssetBomRequest;
use App\Http\Resources\DigitalTwin\AssetBomItemResource;
use App\Models\Asset;
use App\Services\DigitalTwin\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetBomController extends ApiController
{
    public function __construct(private readonly BomService $bomService)
    {
    }

    public function sync(SyncAssetBomRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $items = $this->bomService->syncBom($asset, $request->validated()['items'])->load('part');

        $resources = $items->map(fn ($item) => (new AssetBomItemResource($item))->toArray($request))->all();

        return $this->ok(
            ['items' => $resources],
            'Bill of materials synchronized.'
        );
    }
}

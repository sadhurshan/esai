<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreDigitalTwinAssetRequest;
use App\Http\Resources\DigitalTwinAssetResource;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAsset;
use App\Models\User;
use App\Services\DigitalTwin\DigitalTwinAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DigitalTwinAssetController extends ApiController
{
    public function __construct(private readonly DigitalTwinAssetService $assetService)
    {
    }

    public function store(StoreDigitalTwinAssetRequest $request, DigitalTwin $digitalTwin): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $asset = $this->assetService->store(
            $actor,
            $digitalTwin,
            $request->file('file'),
            $request->validated()
        );

        return $this->ok([
            'asset' => (new DigitalTwinAssetResource($asset))->toArray($request),
        ], 'Digital twin asset uploaded.')->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, DigitalTwin $digitalTwin, DigitalTwinAsset $asset): JsonResponse
    {
        $this->authorize('update', $digitalTwin);

        if ($asset->digital_twin_id !== $digitalTwin->id) {
            return $this->fail('Asset not found for digital twin.', 404);
        }

        $actor = $this->resolveActor($request);

        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $this->assetService->delete($actor, $digitalTwin, $asset);

        return $this->ok(null, 'Digital twin asset deleted.');
    }

    /**
     * @return User|JsonResponse
     */
    private function resolveActor(Request $request): User|JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        return $user;
    }
}

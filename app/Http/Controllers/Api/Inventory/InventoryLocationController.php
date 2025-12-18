<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ListInventoryLocationsAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Inventory\ListInventoryLocationsRequest;
use App\Http\Resources\Inventory\InventoryLocationResource;
use Illuminate\Http\JsonResponse;

class InventoryLocationController extends ApiController
{
    public function __construct(private readonly ListInventoryLocationsAction $listInventoryLocations) {}

    public function index(ListInventoryLocationsRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $paginator = $this->listInventoryLocations
            ->execute(
                $context['companyId'],
                $request->validated(),
                $this->perPage($request, 50, 200),
                $request->query('cursor')
            )
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, InventoryLocationResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Inventory locations retrieved.', $paginated['meta']);
    }
}

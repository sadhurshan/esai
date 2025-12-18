<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ListInventoryMovementsAction;
use App\Actions\Inventory\ShowInventoryMovementAction;
use App\Actions\Inventory\StoreInventoryMovementAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Inventory\ListInventoryMovementsRequest;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\Inventory\InventoryMovementResource;
use Illuminate\Http\JsonResponse;

class InventoryMovementController extends ApiController
{
    public function __construct(
        private readonly ListInventoryMovementsAction $listInventoryMovements,
        private readonly StoreInventoryMovementAction $storeInventoryMovement,
        private readonly ShowInventoryMovementAction $showInventoryMovement,
    ) {}

    public function index(ListInventoryMovementsRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $filters = $request->validated();

        $paginator = $this->listInventoryMovements
            ->execute(
                $context['companyId'],
                $filters,
                $this->perPage($request, 25, 100),
                $request->query('cursor')
            )
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, InventoryMovementResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Inventory movements retrieved.', $paginated['meta']);
    }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $movement = $this->storeInventoryMovement->execute(
            $companyId,
            (int) $request->user()->getKey(),
            $request->payload()
        );

        $resource = $this->showInventoryMovement->execute($companyId, (int) $movement->getKey()) ?? $movement;

        return $this->ok([
            'movement' => (new InventoryMovementResource($resource))->toArray($request),
        ], 'Inventory movement created.')->setStatusCode(201);
    }
}

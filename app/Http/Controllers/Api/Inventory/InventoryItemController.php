<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ListInventoryItemsAction;
use App\Actions\Inventory\ShowInventoryItemAction;
use App\Actions\Inventory\StoreInventoryItemAction;
use App\Actions\Inventory\UpdateInventoryItemAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Inventory\ListInventoryItemsRequest;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Http\Resources\Inventory\InventoryItemResource;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InventoryItemController extends ApiController
{
    public function __construct(
        private readonly ListInventoryItemsAction $listInventoryItems,
        private readonly StoreInventoryItemAction $storeInventoryItem,
        private readonly UpdateInventoryItemAction $updateInventoryItem,
        private readonly ShowInventoryItemAction $showInventoryItem,
    ) {}

    public function index(ListInventoryItemsRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $paginator = $this->listInventoryItems
            ->execute(
                $context['companyId'],
                $request->validated(),
                $this->perPage($request, 25, 100),
                $request->query('cursor')
            )
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, InventoryItemResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Inventory items retrieved.', $paginated['meta']);
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $this->authorize('create', Part::class);

        $part = $this->storeInventoryItem->execute($companyId, $request->payload());

        $resourcePart = $this->showInventoryItem->execute($companyId, (int) $part->getKey());

        $item = $this->transformItem($resourcePart ?? $part, $request);

        return $this->ok([
            'item' => $item,
        ], 'Inventory item created.')->setStatusCode(201);
    }

    public function show(Request $request, int $item): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $part = $this->showInventoryItem->execute($companyId, $item);

        if ($part === null) {
            return $this->fail('Inventory item not found.', 404);
        }

        $this->authorize('view', $part);

        return $this->ok([
            'item' => $this->transformItem($part, $request),
        ], 'Inventory item retrieved.');
    }

    public function update(UpdateInventoryItemRequest $request, int $item): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $part = $this->showInventoryItem->execute($companyId, $item);

        if ($part === null) {
            return $this->fail('Inventory item not found.', 404);
        }

        $this->authorize('update', $part);

        $this->updateInventoryItem->execute($part, $request->payload());

        $refreshed = $this->showInventoryItem->execute($companyId, $item) ?? $part->fresh(['inventorySetting', 'inventories']);

        return $this->ok([
            'item' => $this->transformItem($refreshed ?? $part, $request),
        ], 'Inventory item updated.');
    }

    private function transformItem(Part $part, Request $request): array
    {
        $part->loadMissing([
            'inventorySetting',
            'inventories.warehouse',
            'inventories.bin',
            'documents' => function (MorphMany $documents): void {
                $documents
                    ->latest('created_at')
                    ->limit(50);
            },
        ]);

        return (new InventoryItemResource($part))->toArray($request);
    }
}

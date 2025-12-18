<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ListLowStockItemsAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Inventory\ListLowStockRequest;
use App\Http\Resources\Inventory\LowStockAlertResource;
use Illuminate\Http\JsonResponse;

class InventoryLowStockController extends ApiController
{
    public function __construct(private readonly ListLowStockItemsAction $listLowStock) {}

    public function index(ListLowStockRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $paginator = $this->listLowStock
            ->execute(
                $context['companyId'],
                $request->validated(),
                $this->perPage($request, 25, 100),
                $request->query('cursor')
            )
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, LowStockAlertResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Low stock alerts retrieved.', $paginated['meta']);
    }
}

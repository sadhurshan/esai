<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfq\AwardLineItemsAction;
use App\Exceptions\RfqAwardException;
use App\Http\Requests\Rfq\AwardLinesRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\RFQ;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RfqAwardController extends ApiController
{
    public function __construct(private readonly AwardLineItemsAction $awardLineItemsAction)
    {
    }

    public function awardLines(AwardLinesRequest $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        Gate::forUser($user)->authorize('awardLines', $rfq);

        $awards = $request->validated('awards');

        try {
            $purchaseOrders = $this->awardLineItemsAction->execute($rfq, $awards, $user);
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        } catch (RfqAwardException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus());
        }

        $items = PurchaseOrderResource::collection($purchaseOrders)->resolve();

        return $this->ok([
            'items' => $items,
        ], 'RFQ lines awarded')->setStatusCode(201);
    }
}

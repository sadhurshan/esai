<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RfqItemResource;
use App\Models\RFQ;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqLineController extends ApiController
{
    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user === null
            || $user->company_id === null
            || $rfq->company_id !== $user->company_id,
            403
        );

        $lines = $rfq->items()
            ->orderBy('line_no')
            ->get();

        return $this->ok([
            'items' => RfqItemResource::collection($lines)->resolve(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Receiving\CloseNcrAction;
use App\Actions\Receiving\CreateNcrAction;
use App\Http\Requests\Receiving\StoreNcrRequest;
use App\Http\Requests\Receiving\UpdateNcrRequest;
use App\Http\Resources\NcrResource;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Ncr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NcrController extends ApiController
{
    public function __construct(
        private readonly CreateNcrAction $createAction,
        private readonly CloseNcrAction $closeAction,
    ) {}

    public function store(StoreNcrRequest $request, GoodsReceiptNote $note): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ((int) $note->company_id !== (int) $companyId) {
            return $this->fail('Goods receipt not found.', 404);
        }

        if ($this->authorizeDenied($user, 'create', Ncr::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $poLineId = (int) $request->input('purchase_order_line_id');

        $line = $note->lines()
            ->where('purchase_order_line_id', $poLineId)
            ->first();

        if (! $line instanceof GoodsReceiptLine) {
            return $this->fail('Purchase order line is not part of this goods receipt note.', 422);
        }

        $payload = [
            'reason' => (string) $request->input('reason'),
            'disposition' => $request->input('disposition'),
            'documents' => $request->input('documents', []),
        ];

        $ncr = $this->createAction->execute($user, $note, $line, $payload)->load(['raisedBy']);

        return $this->ok(
            (new NcrResource($ncr))->toArray($request),
            'Non-conformance raised.'
        );
    }

    public function update(UpdateNcrRequest $request, Ncr $ncr): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ((int) $ncr->company_id !== (int) $companyId) {
            return $this->fail('NCR not found.', 404);
        }

        if ($this->authorizeDenied($user, 'update', $ncr)) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = [
            'disposition' => $request->input('disposition'),
        ];

        $ncr = $this->closeAction->execute($user, $ncr, $payload)->load(['raisedBy']);

        return $this->ok(
            (new NcrResource($ncr))->toArray($request),
            'NCR updated.'
        );
    }
}

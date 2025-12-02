<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoicing\RecalculateCreditNoteTotalsAction;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditTotalsController extends ApiController
{
    public function __construct(private readonly RecalculateCreditNoteTotalsAction $recalculateCreditNoteTotals)
    {
    }

    public function recalculate(Request $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null || (int) $companyId !== (int) $creditNote->company_id) {
            return $this->fail('Credit note not found for this company.', 404);
        }

        $creditNote = $this->recalculateCreditNoteTotals->execute($creditNote);

        return $this->ok((new CreditNoteResource($creditNote))->toArray($request), 'Credit note total recalculated.');
    }
}

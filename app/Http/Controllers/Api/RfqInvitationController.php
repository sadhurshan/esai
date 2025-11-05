<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfq\InviteSuppliersToRfqAction;
use App\Enums\CompanySupplierStatus;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\RfqInvitationResource;
use App\Models\RFQ;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqInvitationController extends ApiController
{
    public function __construct(private readonly InviteSuppliersToRfqAction $inviteSuppliersToRfqAction) {}

    public function store(RFQ $rfq, StoreInvitationRequest $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        abort_if(
            $user === null
            || $user->company_id !== $rfq->company_id
            || $user->company === null
            || $user->company->supplier_status !== CompanySupplierStatus::Approved,
            403
        );

        $this->inviteSuppliersToRfqAction->execute(
            $rfq,
            (int) $user->id,
            $request->validated('supplier_ids')
        );

        $rfq->load(['invitations.supplier']);

        return $this->ok([
            'items' => RfqInvitationResource::collection($rfq->invitations)->resolve(),
        ], 'Suppliers invited');
    }

    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            $user === null
            || $user->company_id !== $rfq->company_id
            || $user->company === null
            || $user->company->supplier_status !== CompanySupplierStatus::Approved,
            403
        );

        $paginator = $rfq->invitations()
            ->with(['supplier'])
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RfqInvitationResource::class);

        return $this->ok([
            'items' => $items,
            'meta' => $meta,
        ]);
    }
}

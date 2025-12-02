<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfq\InviteSuppliersToRfqAction;
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
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'manageInvitations', $rfq)) {
            return $this->fail('RFQ invitations require sourcing write access.', 403, [
                'code' => 'rfqs_write_required',
            ]);
        }

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
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'viewInvitations', $rfq)) {
            return $this->fail('RFQ invitations require sourcing write access.', 403, [
                'code' => 'rfqs_write_required',
            ]);
        }

        $paginator = $rfq->invitations()
            ->with(['supplier'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RfqInvitationResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }
}

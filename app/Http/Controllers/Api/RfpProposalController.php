<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfp\SubmitRfpProposalAction;
use App\Http\Requests\Rfp\StoreRfpProposalRequest;
use App\Http\Resources\RfpProposalResource;
use App\Models\Rfp;
use App\Models\RfpProposal;
use App\Support\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RfpProposalController extends ApiController
{
    public function __construct(private readonly SubmitRfpProposalAction $submitRfpProposalAction)
    {
    }

    public function index(Rfp $rfp, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        try {
            Gate::forUser($user)->authorize('viewAny', [RfpProposal::class, $rfp]);
        } catch (AuthorizationException) {
            return $this->fail('RFP access required to review proposals.', 403, [
                'code' => 'rfp_proposal_review_denied',
            ]);
        }

        $proposals = CompanyContext::forCompany((int) $rfp->company_id, static function () use ($rfp) {
            return RfpProposal::query()
                ->where('rfp_id', $rfp->id)
                ->where('company_id', $rfp->company_id)
                ->with(['supplierCompany:id,name'])
                ->orderByDesc('created_at')
                ->get();
        });

        $items = RfpProposalResource::collection($proposals)->toArray($request);

        return $this->ok([
            'items' => $items,
            'summary' => $this->summarize($proposals),
        ]);
    }

    public function store(Rfp $rfp, StoreRfpProposalRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        $payload = $request->validated();
        $supplierCompanyId = (int) ($payload['supplier_company_id'] ?? $user->company_id ?? 0);

        if ($supplierCompanyId <= 0) {
            return $this->fail('Supplier company context is required.', 422, [
                'supplier_company_id' => ['Supplier company context is required.'],
            ]);
        }

        try {
            Gate::forUser($user)->authorize('submit', [RfpProposal::class, $rfp, $supplierCompanyId]);
        } catch (AuthorizationException) {
            return $this->fail('Supplier access required to submit proposals.', 403, [
                'code' => 'rfp_proposal_submit_denied',
            ]);
        }

        /** @var array<int, UploadedFile> $attachments */
        $attachments = collect($request->file('attachments', []))
            ->filter(static fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();

        try {
            $proposal = $this->submitRfpProposalAction->execute($rfp, $user, array_merge($payload, [
                'supplier_company_id' => $supplierCompanyId,
            ]), $attachments);
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new RfpProposalResource($proposal))->toArray($request),
            'Proposal submitted'
        )->setStatusCode(201);
    }
    private function summarize(Collection $proposals): array
    {
        if ($proposals->isEmpty()) {
            return [
                'total' => 0,
            ];
        }

        $prices = $proposals
            ->pluck('price_total_minor')
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (int) $value);

        $leadTimes = $proposals
            ->pluck('lead_time_days')
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (int) $value);

        return [
            'total' => $proposals->count(),
            'min_price_minor' => $prices->min(),
            'max_price_minor' => $prices->max(),
            'min_lead_time_days' => $leadTimes->min(),
            'max_lead_time_days' => $leadTimes->max(),
            'currency' => $proposals->firstWhere('currency')?->currency,
        ];
    }
}

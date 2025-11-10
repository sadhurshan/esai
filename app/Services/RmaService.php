<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\RmaStatus;
use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rma;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RmaService
{
    /**
     * @param  list<string>  $reviewerRoles
     */
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
        private readonly NotificationService $notifications,
        private readonly array $reviewerRoles = ['buyer_admin', 'quality', 'finance'],
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<UploadedFile>  $attachments
     */
    public function createRma(
        Company $company,
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderLine $line,
        ?GoodsReceiptNote $grn,
        User $submitter,
        array $attributes,
        array $attachments = []
    ): Rma {
        $company->loadMissing('plan');

        $plan = $company->plan;
        if ($plan !== null) {
            if (! $plan->rma_enabled) {
                throw ValidationException::withMessages([
                    'plan' => ['Upgrade required to access RMAs.'],
                ]);
            }

            if ((int) $plan->rma_monthly_limit > 0 && (int) $company->rma_monthly_used >= (int) $plan->rma_monthly_limit) {
                throw ValidationException::withMessages([
                    'plan' => ['Monthly RMA limit reached.'],
                ]);
            }
        }

        $this->assertCompanyOwnership($company, $purchaseOrder, $line, $grn);
        $this->assertPurchaseOrderEligible($purchaseOrder, $grn);

        /** @var array<string, mixed> $payload */
        $payload = Arr::only($attributes, [
            'reason',
            'description',
            'resolution_requested',
        ]);

        if (! isset($payload['reason']) || trim((string) $payload['reason']) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Reason is required.'],
            ]);
        }

        if (! in_array($attributes['resolution_requested'] ?? null, ['repair', 'replacement', 'credit', 'refund', 'other'], true)) {
            throw ValidationException::withMessages([
                'resolution_requested' => ['Invalid resolution requested.'],
            ]);
        }

        return DB::transaction(function () use ($company, $purchaseOrder, $line, $grn, $submitter, $payload, $attachments): Rma {
            $rma = Rma::create([
                'company_id' => $company->id,
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_line_id' => $line?->id,
                'grn_id' => $grn?->id,
                'submitted_by' => $submitter->id,
                'reason' => $payload['reason'],
                'description' => $payload['description'] ?? null,
                'resolution_requested' => $payload['resolution_requested'],
                'status' => RmaStatus::Raised,
            ]);

            $this->auditLogger->created($rma, $rma->toArray());

            foreach ($attachments as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $document = $this->documentStorer->store(
                    $submitter,
                    $file,
                    DocumentCategory::Qa->value,
                    $company->id,
                    Rma::class,
                    $rma->id,
                    [
                        'kind' => DocumentKind::Other->value,
                        'visibility' => 'company',
                    ]
                );

                $rma->documents()->attach($document->id);
            }

            $company->increment('rma_monthly_used');

            $this->notifyTeams(
                $company,
                'rma.raised',
                'Return request submitted',
                sprintf('A new RMA has been raised for PO %s.', $purchaseOrder->po_number),
                $rma,
                [
                    'resolution_requested' => $rma->resolution_requested,
                ]
            );

            $this->updateSupplierMetrics($rma);

            return $rma->fresh(['documents']);
        });
    }

    public function reviewRma(Rma $rma, string $decision, ?string $comment, User $reviewer): Rma
    {
        $decision = strtolower($decision);

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Decision must be approve or reject.'],
            ]);
        }

        if (! in_array($reviewer->role, $this->reviewerRoles, true)) {
            throw ValidationException::withMessages([
                'decision' => ['You are not authorised to review RMAs.'],
            ]);
        }

        if (! $rma->isReviewable()) {
            throw ValidationException::withMessages([
                'rma' => ['RMA is not in a reviewable state.'],
            ]);
        }

        return DB::transaction(function () use ($rma, $decision, $comment, $reviewer): Rma {
            if ($rma->status === RmaStatus::Raised) {
                $before = $rma->getOriginal();
                $rma->status = RmaStatus::UnderReview;
                $rma->save();
                $this->auditLogger->updated($rma, $before, $rma->toArray());
            }

            $status = $decision === 'approve' ? RmaStatus::Approved : RmaStatus::Rejected;

            $before = $rma->getOriginal();
            $rma->status = $status;
            $rma->review_outcome = $status === RmaStatus::Approved ? 'approved' : 'rejected';
            $rma->review_comment = $comment;
            $rma->reviewed_by = $reviewer->id;
            $rma->reviewed_at = Carbon::now();
            $rma->save();

            $this->auditLogger->updated($rma, $before, $rma->toArray());

            $this->notifyTeams(
                $rma->company,
                'rma.reviewed',
                'RMA review completed',
                sprintf('RMA #%d has been %s.', $rma->id, $status->value),
                $rma,
                [
                    'decision' => $status->value,
                ]
            );

            if ($status === RmaStatus::Approved && in_array($rma->resolution_requested, ['credit', 'refund'], true)) {
                // TODO: trigger credit note workflow once available.
            }

            $this->closeRma($rma);

            return $rma->fresh(['documents']);
        });
    }

    private function assertCompanyOwnership(
        Company $company,
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderLine $line,
        ?GoodsReceiptNote $grn
    ): void {
        if ((int) $purchaseOrder->company_id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not accessible.'],
            ]);
        }

        if ($line !== null && (int) $line->purchase_order_id !== (int) $purchaseOrder->id) {
            throw ValidationException::withMessages([
                'purchase_order_line_id' => ['Line does not belong to the selected purchase order.'],
            ]);
        }

        if ($grn !== null && (int) $grn->purchase_order_id !== (int) $purchaseOrder->id) {
            throw ValidationException::withMessages([
                'grn_id' => ['Goods receipt note does not match the selected purchase order.'],
            ]);
        }
    }

    private function assertPurchaseOrderEligible(PurchaseOrder $purchaseOrder, ?GoodsReceiptNote $grn): void
    {
        $eligibleStatuses = ['confirmed', 'acknowledged'];
        $hasCompletedGrn = $grn?->status === 'complete'
            || $purchaseOrder->goodsReceiptNotes()->where('status', 'complete')->exists();

        if (! in_array($purchaseOrder->status, $eligibleStatuses, true) && ! $hasCompletedGrn) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['RMA can only be created for delivered orders.'],
            ]);
        }
    }

    private function closeRma(Rma $rma): void
    {
        $before = $rma->getOriginal();
        $rma->status = RmaStatus::Closed;
        $rma->save();

        $this->auditLogger->updated($rma, $before, $rma->toArray());

        $this->notifyTeams(
            $rma->company,
            'rma.closed',
            'RMA closed',
            sprintf('RMA #%d has been closed.', $rma->id),
            $rma,
            [
                'decision' => $rma->review_outcome,
            ]
        );
    }

    private function notifyTeams(
        ?Company $company,
        string $event,
        string $title,
        string $body,
        Rma $rma,
        array $meta = []
    ): void {
        if (! $company instanceof Company) {
            return;
        }

        $recipients = $company->users()
            ->whereIn('role', ['buyer_admin', 'quality', 'finance'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            $event,
            $title,
            $body,
            Rma::class,
            $rma->id,
            $meta
        );
    }

    private function updateSupplierMetrics(Rma $rma): void
    {
        // TODO: wire into supplier risk score calculations once metrics module is ready.
    }
}

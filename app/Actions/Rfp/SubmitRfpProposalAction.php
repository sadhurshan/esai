<?php

namespace App\Actions\Rfp;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\RfpStatus;
use App\Models\Rfp;
use App\Models\RfpProposal;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitRfpProposalAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
    ) {
    }

    /**
     * @param  array<string, mixed>               $attributes
     * @param  array<int, UploadedFile>          $attachments
     */
    public function execute(Rfp $rfp, User $user, array $attributes, array $attachments = []): RfpProposal
    {
        if ($rfp->status !== RfpStatus::Published) {
            throw ValidationException::withMessages([
                'rfp' => ['Proposals can only be submitted to published RFPs.'],
            ]);
        }

        $supplierCompanyId = (int) ($attributes['supplier_company_id'] ?? $user->company_id ?? 0);

        if ($supplierCompanyId <= 0) {
            throw ValidationException::withMessages([
                'supplier_company_id' => ['Supplier company context is required.'],
            ]);
        }

        $companyId = (int) $rfp->company_id;

        return CompanyContext::forCompany($companyId, function () use ($rfp, $user, $attributes, $attachments, $companyId, $supplierCompanyId): RfpProposal {
            return DB::transaction(function () use ($rfp, $user, $attributes, $attachments, $companyId, $supplierCompanyId): RfpProposal {
                $proposal = RfpProposal::create([
                    'rfp_id' => $rfp->id,
                    'company_id' => $companyId,
                    'supplier_company_id' => $supplierCompanyId,
                    'submitted_by' => $user->id,
                    'status' => 'submitted',
                    'price_total' => $attributes['price_total'] ?? null,
                    'price_total_minor' => $attributes['price_total_minor'] ?? null,
                    'currency' => isset($attributes['currency']) ? strtoupper((string) $attributes['currency']) : null,
                    'lead_time_days' => $attributes['lead_time_days'] ?? null,
                    'approach_summary' => (string) ($attributes['approach_summary'] ?? ''),
                    'schedule_summary' => (string) ($attributes['schedule_summary'] ?? ''),
                    'value_add_summary' => $attributes['value_add_summary'] ?? null,
                    'meta' => $attributes['meta'] ?? [],
                ]);

                $storedAttachments = $this->storeAttachments($proposal, $user, $attachments);

                if ($storedAttachments > 0) {
                    $proposal->forceFill([
                        'attachments_count' => (int) $proposal->attachments_count + $storedAttachments,
                    ])->save();
                }

                $proposal->refresh();

                $this->auditLogger->created($proposal, [
                    'rfp_id' => $rfp->id,
                    'supplier_company_id' => $supplierCompanyId,
                ]);

                return $proposal->load(['supplierCompany', 'documents']);
            });
        });
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    private function storeAttachments(RfpProposal $proposal, User $user, array $attachments): int
    {
        if ($attachments === []) {
            return 0;
        }

        $stored = 0;

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->documentStorer->store(
                $user,
                $file,
                DocumentCategory::Commercial->value,
                $proposal->company_id,
                $proposal->getMorphClass(),
                $proposal->id,
                [
                    'kind' => DocumentKind::RfpProposal->value,
                    'visibility' => 'company',
                    'meta' => [
                        'context' => 'rfp_proposal_attachment',
                        'rfp_id' => $proposal->rfp_id,
                    ],
                ]
            );

            $stored++;
        }

        return $stored;
    }
}

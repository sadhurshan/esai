<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\RfqClarificationType;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfqClarificationService
{
    /**
     * @var list<string>
     */
    private array $buyerRoles = ['owner', 'buyer_admin', 'buyer_requester'];

    /**
     * @var list<string>
     */
    private array $supplierRoles = ['supplier_admin', 'supplier_estimator'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly DocumentStorer $documentStorer,
    ) {
    }

    /**
     * @param list<UploadedFile|null> $attachments
     */
    public function postQuestion(RFQ $rfq, User $user, string $message, array $attachments = []): RfqClarification
    {
        $this->assertQuestionAccess($rfq, $user);

        $clarification = $this->storeClarification(
            $rfq,
            $user,
            $message,
            $attachments,
            RfqClarificationType::Question,
            false,
        );

        $this->notifyParticipants(
            $rfq,
            $user,
            'rfq.clarification.question',
            'RFQ question posted',
            $message,
            $clarification,
            $this->resolveBuyerParticipants($rfq)
                ->merge($this->resolveSupplierParticipants($rfq))
        );

        return $clarification;
    }

    /**
     * @param list<UploadedFile|null> $attachments
     */
    public function postAnswer(RFQ $rfq, User $user, string $message, array $attachments = []): RfqClarification
    {
        $this->assertBuyerAccess($rfq, $user);

        $clarification = $this->storeClarification(
            $rfq,
            $user,
            $message,
            $attachments,
            RfqClarificationType::Answer,
            false,
        );

        $this->notifyParticipants(
            $rfq,
            $user,
            'rfq.clarification.answer',
            'RFQ clarification answered',
            $message,
            $clarification,
            $this->resolveSupplierParticipants($rfq)
                ->merge($this->resolveBuyerParticipants($rfq))
        );

        return $clarification;
    }

    /**
     * @param list<UploadedFile|null> $attachments
     */
    public function postAmendment(RFQ $rfq, User $user, string $message, array $attachments = []): RfqClarification
    {
        $this->assertBuyerAccess($rfq, $user);

        if (in_array($rfq->status, ['closed', 'awarded', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'rfq' => ['Amendments cannot be posted to closed or awarded RFQs.'],
            ]);
        }

        $clarification = $this->storeClarification(
            $rfq,
            $user,
            $message,
            $attachments,
            RfqClarificationType::Amendment,
            true,
        );

        $this->notifyParticipants(
            $rfq,
            $user,
            'rfq.clarification.amendment',
            'RFQ amended',
            $message,
            $clarification,
            $this->resolveSupplierParticipants($rfq)
                ->merge($this->resolveBuyerParticipants($rfq))
        );

        return $clarification;
    }

    /**
     * @param list<UploadedFile|null> $attachments
     */
    private function storeClarification(
        RFQ $rfq,
        User $user,
        string $message,
        array $attachments,
        RfqClarificationType $type,
        bool $versionIncrement
    ): RfqClarification {
        return DB::transaction(function () use ($rfq, $user, $message, $attachments, $type, $versionIncrement): RfqClarification {
            $attachmentPayloads = $this->storeAttachments($rfq, $user, $attachments);
            $currentVersion = $rfq->rfq_version ?? 1;
            $targetVersion = $versionIncrement ? $currentVersion + 1 : $currentVersion;

            $clarification = RfqClarification::create([
                'company_id' => $rfq->company_id,
                'rfq_id' => $rfq->id,
                'user_id' => $user->id,
                'type' => $type,
                'message' => $message,
                'attachments_json' => $attachmentPayloads,
                'version_increment' => $versionIncrement,
                'version_no' => $versionIncrement ? $targetVersion : $currentVersion,
            ]);

            $this->auditLogger->created($clarification, [
                'rfq_id' => $rfq->id,
                'type' => $clarification->type->value,
                'version_no' => $clarification->version_no,
            ]);

            if ($versionIncrement) {
                $before = [
                    'rfq_version' => $rfq->rfq_version,
                    'current_revision_id' => $rfq->current_revision_id,
                ];

                $rfq->rfq_version = $targetVersion;
                $rfq->current_revision_id = $clarification->id;
                $rfq->save();

                $this->auditLogger->updated($rfq, $before, [
                    'rfq_version' => $rfq->rfq_version,
                    'current_revision_id' => $clarification->id,
                ]);
            }

            return $clarification->fresh(['user']);
        });
    }

    /**
     * @param list<UploadedFile|null> $attachments
     * @return list<array<string, mixed>>
     */
    private function storeAttachments(RFQ $rfq, User $user, array $attachments): array
    {
        $payloads = [];

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $document = $this->documentStorer->store(
                $user,
                $file,
                DocumentCategory::Communication->value,
                $rfq->company_id,
                $rfq->getMorphClass(),
                $rfq->id,
                [
                    'kind' => DocumentKind::Rfq->value,
                    'visibility' => 'company',
                ]
            );

            $payloads[] = [
                'document_id' => $document->id,
                'filename' => $document->filename,
                'mime' => $document->mime,
                'size_bytes' => (int) ($document->size_bytes ?? 0),
                'uploaded_by' => $user->id,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ];
        }

        return $payloads;
    }

    private function notifyParticipants(
        RFQ $rfq,
        User $sender,
        string $event,
        string $title,
        string $message,
        RfqClarification $clarification,
        Collection $recipients
    ): void {
        $uniqueRecipients = $recipients
            ->filter(static fn ($user) => $user instanceof User)
            ->reject(static fn (User $user) => $user->id === $sender->id)
            ->unique('id')
            ->values();

        if ($uniqueRecipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $uniqueRecipients,
            $event,
            $title,
            $message,
            RFQ::class,
            $rfq->id,
            [
                'clarification_id' => $clarification->id,
                'type' => $clarification->type->value,
                'version_no' => $clarification->version_no,
            ]
        );
    }

    private function resolveBuyerParticipants(RFQ $rfq): Collection
    {
        return User::query()
            ->where('company_id', $rfq->company_id)
            ->whereIn('role', $this->buyerRoles)
            ->get();
    }

    private function resolveSupplierParticipants(RFQ $rfq): Collection
    {
        $companyIds = $this->invitedSupplierCompanyIds($rfq);

        if ($companyIds === []) {
            // TODO: clarify how open bidding broadcasts supplier notifications to avoid spamming the entire network.
            return collect();
        }

        return User::query()
            ->whereIn('company_id', $companyIds)
            ->whereIn('role', $this->supplierRoles)
            ->get();
    }

    private function assertQuestionAccess(RFQ $rfq, User $user): void
    {
        if ($this->isPlatformRole($user)) {
            return;
        }

        if ($this->belongsToBuyerCompany($rfq, $user) && ($this->isBuyerRole($user) || $this->isSupplierRole($user))) {
            return;
        }

        if ($this->isSupplierRole($user) && $this->supplierHasInvitationAccess($rfq, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You do not have access to this RFQ.'],
        ]);
    }

    private function assertBuyerAccess(RFQ $rfq, User $user): void
    {
        if ($this->isPlatformRole($user)) {
            return;
        }

        if ($this->belongsToBuyerCompany($rfq, $user) && $this->isBuyerRole($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['Only buyer roles can perform this action.'],
        ]);
    }

    private function belongsToBuyerCompany(RFQ $rfq, User $user): bool
    {
        return $user->company_id !== null && (int) $rfq->company_id === (int) $user->company_id;
    }

    private function supplierHasInvitationAccess(RFQ $rfq, User $user): bool
    {
        if (! $this->isSupplierRole($user)) {
            return false;
        }

        if ((bool) $rfq->is_open_bidding) {
            return true;
        }

        if ($user->company_id === null) {
            return false;
        }

        $supplierIds = $this->supplierIdsForCompany((int) $user->company_id);

        if ($supplierIds === []) {
            return false;
        }

        $invitedSupplierIds = $this->invitedSupplierIds($rfq);

        return array_intersect($supplierIds, $invitedSupplierIds) !== [];
    }

    /**
     * @return list<int>
     */
    private function supplierIdsForCompany(int $companyId): array
    {
        return CompanyContext::bypass(static function () use ($companyId): array {
            return Supplier::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
        });
    }

    /**
     * @return list<int>
     */
    private function invitedSupplierIds(RFQ $rfq): array
    {
        $rfq->loadMissing('invitations');

        return $rfq->invitations
            ->pluck('supplier_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function invitedSupplierCompanyIds(RFQ $rfq): array
    {
        $supplierIds = $this->invitedSupplierIds($rfq);

        if ($supplierIds === []) {
            return [];
        }

        return CompanyContext::bypass(static function () use ($supplierIds): array {
            return Supplier::query()
                ->whereIn('id', $supplierIds)
                ->pluck('company_id')
                ->filter()
                ->map(static fn ($companyId) => (int) $companyId)
                ->unique()
                ->values()
                ->all();
        });
    }

    private function isBuyerRole(User $user): bool
    {
        return in_array($user->role, $this->buyerRoles, true);
    }

    private function isSupplierRole(User $user): bool
    {
        return in_array($user->role, $this->supplierRoles, true);
    }

    private function isPlatformRole(User $user): bool
    {
        return in_array($user->role, ['platform_super', 'platform_support'], true);
    }
}

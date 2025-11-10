<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\RfqClarificationType;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\User;
use App\Support\Audit\AuditLogger;
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
        $this->assertSameCompany($rfq, $user);
        $this->assertCanParticipate($rfq, $user);

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
        $this->assertSameCompany($rfq, $user);
        $this->assertBuyerRole($user);

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
        $this->assertSameCompany($rfq, $user);
        $this->assertBuyerRole($user);

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
            $attachmentIds = $this->storeAttachments($rfq, $user, $attachments);
            $currentVersion = $rfq->version_no ?? $rfq->version ?? 1;
            $targetVersion = $versionIncrement ? $currentVersion + 1 : $currentVersion;

            $clarification = RfqClarification::create([
                'company_id' => $rfq->company_id,
                'rfq_id' => $rfq->id,
                'user_id' => $user->id,
                'type' => $type,
                'message' => $message,
                'attachments_json' => $attachmentIds,
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
                    'version_no' => $rfq->version_no,
                    'version' => $rfq->version,
                    'current_revision_id' => $rfq->current_revision_id,
                ];

                $rfq->version_no = $targetVersion;
                $rfq->version = $targetVersion;
                $rfq->current_revision_id = $clarification->id;
                $rfq->save();

                $this->auditLogger->updated($rfq, $before, [
                    'version_no' => $rfq->version_no,
                    'version' => $rfq->version,
                    'current_revision_id' => $clarification->id,
                ]);
            }

            return $clarification->fresh(['user']);
        });
    }

    /**
     * @param list<UploadedFile|null> $attachments
     * @return list<int>
     */
    private function storeAttachments(RFQ $rfq, User $user, array $attachments): array
    {
        $ids = [];

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

            $ids[] = $document->id;
        }

        return $ids;
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
        // TODO: clarify with spec - supplier user invitations should be linked for finer access control.
        return User::query()
            ->where('company_id', $rfq->company_id)
            ->whereIn('role', $this->supplierRoles)
            ->get();
    }

    private function assertSameCompany(RFQ $rfq, User $user): void
    {
        if ((int) $rfq->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'rfq' => ['You do not have access to this RFQ.'],
            ]);
        }
    }

    private function assertBuyerRole(User $user): void
    {
        if (! in_array($user->role, [...$this->buyerRoles, 'platform_super', 'platform_support'], true)) {
            throw ValidationException::withMessages([
                'user' => ['Only buyer roles can perform this action.'],
            ]);
        }
    }

    private function assertCanParticipate(RFQ $rfq, User $user): void
    {
        if ($user->role === null) {
            throw ValidationException::withMessages([
                'user' => ['Your role does not allow participation in clarifications.'],
            ]);
        }

        if (in_array($user->role, array_merge($this->buyerRoles, $this->supplierRoles, ['platform_super', 'platform_support']), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['Your role does not allow participation in clarifications.'],
        ]);
    }
}

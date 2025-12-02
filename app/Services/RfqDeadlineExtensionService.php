<?php

namespace App\Services;

use App\Models\RFQ;
use App\Models\RfqDeadlineExtension;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Notifications\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfqDeadlineExtensionService
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
        private readonly NotificationService $notifications
    ) {
    }

    public function extend(RFQ $rfq, User $user, CarbonInterface $newDueAt, string $reason, bool $notifySuppliers = true): RfqDeadlineExtension
    {
        $this->assertExtendable($rfq, $newDueAt);

        return DB::transaction(function () use ($rfq, $user, $newDueAt, $reason, $notifySuppliers): RfqDeadlineExtension {
            $extension = RfqDeadlineExtension::create([
                'company_id' => $rfq->company_id,
                'rfq_id' => $rfq->id,
                'previous_due_at' => $rfq->due_at,
                'new_due_at' => $newDueAt,
                'reason' => $reason,
                'extended_by' => $user->id,
            ]);

            $before = Arr::only($rfq->getAttributes(), ['due_at', 'close_at']);

            $rfq->due_at = $newDueAt;
            $rfq->close_at = $newDueAt;
            $rfq->save();

            $this->auditLogger->created($extension, [
                'rfq_id' => $rfq->id,
                'previous_due_at' => $extension->previous_due_at?->toIso8601String(),
                'new_due_at' => $extension->new_due_at?->toIso8601String(),
            ]);

            $this->auditLogger->updated($rfq, $before, Arr::only($rfq->getAttributes(), ['due_at', 'close_at']));

            if ($notifySuppliers) {
                $this->notifyParticipants($rfq, $user, $extension);
            }

            return $extension->fresh(['extendedBy']);
        });
    }

    private function assertExtendable(RFQ $rfq, CarbonInterface $newDueAt): void
    {
        $allowedStatuses = [RFQ::STATUS_OPEN, 'awaiting'];

        if (! in_array($rfq->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'rfq' => ['Deadline can only be extended while the RFQ is open.'],
            ]);
        }

        if ($rfq->due_at !== null && $newDueAt->lessThanOrEqualTo($rfq->due_at)) {
            throw ValidationException::withMessages([
                'new_due_at' => ['New deadline must be later than the current deadline.'],
            ]);
        }

        if ($newDueAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'new_due_at' => ['New deadline must be in the future.'],
            ]);
        }
    }

    private function notifyParticipants(RFQ $rfq, User $actor, RfqDeadlineExtension $extension): void
    {
        $recipients = $this->buyerParticipants($rfq)
            ->merge($this->supplierParticipants($rfq))
            ->filter(static fn ($user) => $user instanceof User)
            ->reject(static fn (User $user) => $user->id === $actor->id)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'RFQ deadline extended';
        $body = sprintf(
            '%s extended the RFQ deadline to %s.',
            $actor->name ?? 'A buyer',
            $extension->new_due_at?->toDayDateTimeString()
        );

        $this->notifications->send(
            $recipients,
            'rfq.deadline.extended',
            $title,
            $body,
            RFQ::class,
            $rfq->id,
            [
                'rfq_id' => $rfq->id,
                'extension_id' => $extension->id,
                'previous_due_at' => $extension->previous_due_at?->toIso8601String(),
                'new_due_at' => $extension->new_due_at?->toIso8601String(),
            ]
        );
    }

    private function buyerParticipants(RFQ $rfq): Collection
    {
        return User::query()
            ->where('company_id', $rfq->company_id)
            ->whereIn('role', $this->buyerRoles)
            ->get();
    }

    private function supplierParticipants(RFQ $rfq): Collection
    {
        $companyIds = $this->invitedSupplierCompanyIds($rfq);

        if ($companyIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('company_id', $companyIds)
            ->whereIn('role', $this->supplierRoles)
            ->get();
    }

    /**
     * @return list<int>
     */
    private function invitedSupplierCompanyIds(RFQ $rfq): array
    {
        $rfq->loadMissing('invitations');

        $supplierIds = $rfq->invitations
            ->pluck('supplier_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

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
}

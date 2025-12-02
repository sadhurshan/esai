<?php

namespace App\Actions\Rfp;

use App\Enums\RfpStatus;
use App\Models\Rfp;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class TransitionRfpStatusAction
{
    /**
     * @var array<string, list<RfpStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => [RfpStatus::Published],
        'published' => [RfpStatus::InReview],
        'in_review' => [RfpStatus::Awarded, RfpStatus::NoAward],
    ];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function execute(Rfp $rfp, RfpStatus $targetStatus, User $actor): Rfp
    {
        $currentStatus = $rfp->status instanceof RfpStatus
            ? $rfp->status
            : RfpStatus::from($rfp->status);

        if ($currentStatus === $targetStatus) {
            return $rfp->refresh();
        }

        if (! $this->isTransitionAllowed($currentStatus, $targetStatus)) {
            throw new InvalidArgumentException(sprintf(
                'Transition from %s to %s is not allowed.',
                $currentStatus->value,
                $targetStatus->value
            ));
        }

        $previousAttributes = ['status' => $rfp->getOriginal('status')];

        $rfp->status = $targetStatus;
        $rfp->updated_by = $actor->id;

        $now = CarbonImmutable::now();

        if ($targetStatus === RfpStatus::Published) {
            $rfp->published_at = $rfp->published_at ?? $now;
        }

        if ($targetStatus === RfpStatus::InReview) {
            $rfp->in_review_at = $rfp->in_review_at ?? $now;
        }

        if ($targetStatus === RfpStatus::Awarded) {
            $rfp->awarded_at = $rfp->awarded_at ?? $now;
            $rfp->closed_at = $rfp->closed_at ?? $now;
        }

        if ($targetStatus === RfpStatus::NoAward) {
            $rfp->closed_at = $rfp->closed_at ?? $now;
        }

        $rfp->save();
        $rfp->refresh();

        $this->auditLogger->updated(
            $rfp,
            $previousAttributes,
            Arr::only($rfp->getAttributes(), ['status', 'published_at', 'in_review_at', 'awarded_at', 'closed_at'])
        );

        return $rfp;
    }

    private function isTransitionAllowed(RfpStatus $current, RfpStatus $target): bool
    {
        $allowedTargets = self::ALLOWED_TRANSITIONS[$current->value] ?? [];

        foreach ($allowedTargets as $allowed) {
            if ($allowed === $target) {
                return true;
            }
        }

        return false;
    }
}

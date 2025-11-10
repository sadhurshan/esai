<?php

namespace App\Policies;

use App\Models\RFQ;
use App\Models\User;

class RfqClarificationPolicy
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator'];
    private const PLATFORM_ROLES = ['platform_super', 'platform_support'];

    public function viewClarifications(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return false;
        }

        if (in_array($user->role, [...self::BUYER_ROLES, ...self::SUPPLIER_ROLES, ...self::PLATFORM_ROLES], true)) {
            return true;
        }

        return false;
    }

    public function postQuestion(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        if (in_array($user->role, [...self::BUYER_ROLES, ...self::SUPPLIER_ROLES], true)) {
            return true;
        }

        if (in_array($user->role, self::PLATFORM_ROLES, true)) {
            return true;
        }

        // TODO: clarify with spec - supplier invitations should drive access beyond coarse role checks.
        return false;
    }

    public function postAnswer(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return in_array($user->role, [...self::BUYER_ROLES, ...self::PLATFORM_ROLES], true);
    }

    public function postAmendment(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return in_array($user->role, [...self::BUYER_ROLES, ...self::PLATFORM_ROLES], true);
    }

    public function awardLines(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return false;
        }

        if (in_array($user->role, ['owner', 'buyer_admin', 'buyer_requester'], true)) {
            return true;
        }

        return in_array($user->role, self::PLATFORM_ROLES, true);
    }

    private function belongsToCompany(User $user, RFQ $rfq): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $rfq->company_id;
    }
}

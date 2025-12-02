<?php

namespace App\Policies;

use App\Models\RFQ;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

class RfqPolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function create(User $user): bool
    {
        $companyId = $user->company_id;

        if ($companyId === null) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $companyId);
    }

    public function update(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function view(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.read', (int) $rfq->company_id);
    }

    public function delete(User $user, RFQ $rfq): bool
    {
        return $this->update($user, $rfq);
    }

    public function publish(User $user, RFQ $rfq): bool
    {
        return $this->update($user, $rfq);
    }

    public function viewClarifications(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.read', (int) $rfq->company_id);
    }

    public function postQuestion(User $user, RFQ $rfq): bool
    {
        return $this->hasPermission($user, 'rfqs.read', (int) $rfq->company_id);
    }

    public function postAnswer(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function postAmendment(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function extendDeadline(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function awardLines(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function manageInvitations(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    public function viewInvitations(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfqs.write', (int) $rfq->company_id);
    }

    private function belongsToCompany(User $user, RFQ $rfq): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $rfq->company_id;
    }


    private function hasPermission(User $user, string $permission, int $companyId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], $companyId);
    }
}

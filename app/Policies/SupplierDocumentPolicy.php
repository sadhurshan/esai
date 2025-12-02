<?php

namespace App\Policies;

use App\Models\SupplierDocument;
use App\Models\User;

class SupplierDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToCompany($user) && $this->canManage($user);
    }

    public function view(User $user, SupplierDocument $document): bool
    {
        return $this->belongsToCompany($user)
            && (int) $document->company_id === (int) $user->company_id
            && $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->belongsToCompany($user) && $this->canManage($user);
    }

    public function delete(User $user, SupplierDocument $document): bool
    {
        return $this->create($user)
            && (int) $document->company_id === (int) $user->company_id;
    }

    private function belongsToCompany(User $user): bool
    {
        return $user->company_id !== null;
    }

    private function canManage(User $user): bool
    {
        return in_array($user->role, ['owner', 'supplier_admin'], true);
    }
}

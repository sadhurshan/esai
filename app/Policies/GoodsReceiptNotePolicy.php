<?php

namespace App\Policies;

use App\Models\GoodsReceiptNote;
use App\Models\User;

class GoodsReceiptNotePolicy
{
    private const ALLOWED_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];

    public function viewAny(User $user): bool
    {
        return $this->hasRole($user) && $user->company_id !== null;
    }

    public function view(User $user, GoodsReceiptNote $note): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $note);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user) && $user->company_id !== null;
    }

    public function update(User $user, GoodsReceiptNote $note): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $note) && $note->status === 'pending';
    }

    public function delete(User $user, GoodsReceiptNote $note): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $note) && $note->status === 'pending';
    }

    public function attachFile(User $user, GoodsReceiptNote $note): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $note);
    }

    private function hasRole(User $user): bool
    {
        return in_array($user->role, self::ALLOWED_ROLES, true);
    }

    private function matchesCompany(User $user, GoodsReceiptNote $note): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $note->company_id;
    }
}

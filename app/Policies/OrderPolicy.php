<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->userBelongsToCompany($user, $order->company_id)
            || $this->userBelongsToCompany($user, $order->supplier_company_id);
    }

    private function userBelongsToCompany(User $user, ?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        if ($user->company_id !== null && (int) $user->company_id === (int) $companyId) {
            return true;
        }

        return DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }
}

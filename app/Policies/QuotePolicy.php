<?php

namespace App\Policies;

use App\Models\RFQ;
use App\Models\User;

class QuotePolicy
{
    public function submit(User $user, RFQ $rfq, int $supplierId): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        if ($rfq->is_open_bidding) {
            return true;
        }

        return $rfq->invitations()
            ->where('supplier_id', $supplierId)
            ->exists();
    }
}

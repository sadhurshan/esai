<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyPendingVerification
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Ensure listeners run after the database transaction commits.
     */
    public bool $afterCommit = true;

    public function __construct(public Company $company)
    {
    }
}

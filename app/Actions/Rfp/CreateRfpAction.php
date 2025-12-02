<?php

namespace App\Actions\Rfp;

use App\Enums\RfpStatus;
use App\Models\Rfp;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use InvalidArgumentException;

class CreateRfpAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes): Rfp
    {
        $rfp = new Rfp();
        $rfp->fill($attributes);

        $companyId = $attributes['company_id'] ?? $user->company_id ?? CompanyContext::get();

        if ($companyId === null) {
            throw new InvalidArgumentException('Active company context required to create an RFP.');
        }

        $rfp->company_id = (int) $companyId;
        $rfp->created_by = $user->id;
        $rfp->updated_by = $user->id;
        $rfp->status = $attributes['status'] ?? RfpStatus::Draft->value;
        $rfp->save();

        $rfp->refresh();

        $this->auditLogger->created($rfp);

        return $rfp;
    }
}

<?php

namespace App\Actions\Rfp;

use App\Models\Rfp;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;

class UpdateRfpAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Rfp $rfp, array $attributes, User $user): Rfp
    {
        if ($attributes === []) {
            return $rfp->refresh();
        }

        $rfp->fill($attributes);
        $dirty = $rfp->getDirty();

        if ($dirty === []) {
            return $rfp->refresh();
        }

        $before = Arr::only($rfp->getOriginal(), array_keys($dirty));
        $rfp->updated_by = $user->id;
        $rfp->save();
        $rfp->refresh();

        $this->auditLogger->updated(
            $rfp,
            $before,
            Arr::only($rfp->getAttributes(), array_keys($dirty))
        );

        return $rfp;
    }
}

<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Delegation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DelegationService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes, User $actor): Delegation
    {
        $startsAt = Carbon::parse($attributes['starts_at']);
        $endsAt = Carbon::parse($attributes['ends_at']);

        $this->assertDateRange($startsAt, $endsAt);
        $this->assertNoOverlap($company->id, (int) $attributes['approver_user_id'], $startsAt, $endsAt, null);

        $delegation = null;

        DB::transaction(function () use ($company, $attributes, $actor, $startsAt, $endsAt, &$delegation): void {
            $delegation = Delegation::create([
                'company_id' => $company->id,
                'approver_user_id' => (int) $attributes['approver_user_id'],
                'delegate_user_id' => (int) $attributes['delegate_user_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->created($delegation);
        });

        return $delegation;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Delegation $delegation, array $attributes, User $actor): Delegation
    {
        $startsAt = Carbon::parse($attributes['starts_at']);
        $endsAt = Carbon::parse($attributes['ends_at']);

        $this->assertDateRange($startsAt, $endsAt);
        $this->assertNoOverlap($delegation->company_id, (int) $attributes['approver_user_id'], $startsAt, $endsAt, $delegation->id);

        $before = $delegation->getOriginal();

        $delegation->fill([
            'approver_user_id' => (int) $attributes['approver_user_id'],
            'delegate_user_id' => (int) $attributes['delegate_user_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $delegation->save();

        $this->auditLogger->updated($delegation, $before, $delegation->toArray());

        return $delegation;
    }

    public function delete(Delegation $delegation, User $actor): void
    {
        $before = $delegation->getOriginal();
        $delegation->delete();
        $this->auditLogger->deleted($delegation, $before);
    }

    public function resolveActiveDelegate(int $companyId, int $approverUserId, Carbon $onDate): ?Delegation
    {
        return Delegation::query()
            ->where('company_id', $companyId)
            ->where('approver_user_id', $approverUserId)
            ->whereDate('starts_at', '<=', $onDate)
            ->whereDate('ends_at', '>=', $onDate)
            ->orderByDesc('starts_at')
            ->with(['delegate'])
            ->first();
    }

    private function assertDateRange(Carbon $startsAt, Carbon $endsAt): void
    {
        if ($endsAt->isBefore($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['End date must be after the start date.'],
            ]);
        }
    }

    private function assertNoOverlap(int $companyId, int $approverId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreId): void
    {
        $overlapExists = Delegation::query()
            ->where('company_id', $companyId)
            ->where('approver_user_id', $approverId)
            ->when($ignoreId !== null, static fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query->whereBetween('starts_at', [$startsAt, $endsAt])
                    ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                    ->orWhere(function ($subQuery) use ($startsAt, $endsAt): void {
                        $subQuery->where('starts_at', '<=', $startsAt)
                            ->where('ends_at', '>=', $endsAt);
                    });
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'starts_at' => ['Delegation overlaps an existing delegation for this approver.'],
            ]);
        }
    }
}

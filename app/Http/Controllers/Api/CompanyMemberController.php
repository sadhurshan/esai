<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Company\UpdateCompanyMemberRequest;
use App\Http\Resources\CompanyMemberResource;
use App\Models\Company;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyMemberController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $perPage = $this->perPage($request, 25, 100);

        $members = $this->memberQuery($companyId)
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        ['items' => $items, 'meta' => $meta] = $this->paginate($members, $request, CompanyMemberResource::class);

        return $this->ok(
            ['items' => $items],
            'Company members retrieved.',
            $meta
        );
    }

    public function update(UpdateCompanyMemberRequest $request, User $member): JsonResponse
    {
        $actor = $this->resolveRequestUser($request);

        if ($actor === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($actor);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $membership = $this->findMembership($companyId, $member->id);

        if ($membership === null) {
            return $this->fail('Member not found.', 404);
        }

        if ($membership->role === 'owner' && $actor->role !== 'owner') {
            return $this->fail('Only owners may modify owner roles.', 403);
        }

        $nextRole = $request->validated('role');

        if ($nextRole === 'owner' && $actor->role !== 'owner') {
            return $this->fail('Only owners may assign the owner role.', 403);
        }

        $previousRole = $membership->role;
        $roleChanged = $previousRole !== $nextRole;

        try {
            DB::transaction(function () use ($companyId, $member, $nextRole, $previousRole): void {
                $lockedMembership = DB::table('company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $member->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedMembership === null) {
                    throw new \RuntimeException('Membership not found during update.');
                }

                if ($lockedMembership->role === 'owner' && $nextRole !== 'owner') {
                    $otherOwners = DB::table('company_user')
                        ->where('company_id', $companyId)
                        ->where('role', 'owner')
                        ->where('user_id', '!=', $member->id)
                        ->count();

                    if ($otherOwners === 0) {
                        throw new \RuntimeException('Each company must retain at least one owner.');
                    }
                }

                if ($lockedMembership->role !== $nextRole) {
                    DB::table('company_user')
                        ->where('id', $lockedMembership->id)
                        ->update([
                            'role' => $nextRole,
                            'updated_at' => now(),
                        ]);
                }

                $company = Company::query()
                    ->whereKey($companyId)
                    ->lockForUpdate()
                    ->first();

                if ($company === null) {
                    throw new \RuntimeException('Company not found.');
                }

                $this->syncActiveUserRole($member, $companyId, $nextRole);
                $this->syncCompanyOwner($company, $member->id, $previousRole, $nextRole);
            });
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        if ($roleChanged) {
            $this->auditLogger->custom($member->fresh(), 'company_member_role_updated', [
                'company_id' => $companyId,
                'previous_role' => $previousRole,
                'role' => $nextRole,
                'changed_by' => $actor->id,
            ]);
        }

        $updated = $this->resolveMember($companyId, $member->id);

        if ($updated === null) {
            return $this->fail('Member not found after update.', 404);
        }

        return $this->ok(new CompanyMemberResource($updated), 'Member updated.');
    }

    public function destroy(Request $request, User $member): JsonResponse
    {
        $actor = $this->resolveRequestUser($request);

        if ($actor === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($actor);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $membership = $this->findMembership($companyId, $member->id);

        if ($membership === null) {
            return $this->fail('Member not found.', 404);
        }

        if ($membership->role === 'owner' && $actor->role !== 'owner') {
            return $this->fail('Only owners may remove owner memberships.', 403);
        }

        try {
            DB::transaction(function () use ($companyId, $member, $membership): void {
                $lockedMembership = DB::table('company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $member->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedMembership === null) {
                    throw new \RuntimeException('Membership not found during removal.');
                }

                if ($lockedMembership->role === 'owner') {
                    $otherOwners = DB::table('company_user')
                        ->where('company_id', $companyId)
                        ->where('role', 'owner')
                        ->where('user_id', '!=', $member->id)
                        ->count();

                    if ($otherOwners === 0) {
                        throw new \RuntimeException('Each company must retain at least one owner.');
                    }
                }

                DB::table('company_user')
                    ->where('id', $lockedMembership->id)
                    ->delete();

                $company = Company::query()
                    ->whereKey($companyId)
                    ->lockForUpdate()
                    ->first();

                if ($company === null) {
                    throw new \RuntimeException('Company not found.');
                }

                $this->syncCompanyOwner($company, $member->id, $membership->role, null);
                $this->reassignActiveMembership($member, $companyId);
            });
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        $this->auditLogger->custom($member->fresh(), 'company_member_removed', [
            'company_id' => $companyId,
            'role' => $membership->role,
            'removed_by' => $actor->id,
        ]);

        return $this->ok(null, 'Member removed.');
    }

    protected function memberQuery(int $companyId): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('company_user as cu', function (JoinClause $join) use ($companyId): void {
                $join->on('cu.user_id', '=', 'users.id')
                    ->where('cu.company_id', '=', $companyId);
            })
            ->addSelect([
                'membership_id' => 'cu.id',
                'membership_company_id' => 'cu.company_id',
                'membership_role' => 'cu.role',
                'membership_is_default' => 'cu.is_default',
                'membership_last_used_at' => 'cu.last_used_at',
                'membership_created_at' => 'cu.created_at',
                'membership_updated_at' => 'cu.updated_at',
                'membership_role_list' => DB::table('company_user as cu_roles')
                    ->selectRaw($this->roleListAggregateExpression())
                    ->whereColumn('cu_roles.user_id', 'users.id'),
                'membership_company_total' => DB::table('company_user as cu_total')
                    ->selectRaw('COUNT(DISTINCT cu_total.company_id)')
                    ->whereColumn('cu_total.user_id', 'users.id'),
            ]);
    }

    protected function resolveMember(int $companyId, int $userId): ?User
    {
        return $this->memberQuery($companyId)
            ->where('users.id', $userId)
            ->first();
    }

    private function findMembership(int $companyId, int $userId): ?object
    {
        return DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();
    }

    private function syncActiveUserRole(User $member, int $companyId, string $role): void
    {
        if ((int) $member->company_id !== $companyId) {
            return;
        }

        if ($member->role === $role) {
            return;
        }

        $member->forceFill(['role' => $role])->save();
    }

    private function syncCompanyOwner(Company $company, int $userId, ?string $previousRole, ?string $nextRole): void
    {
        $currentOwnerId = $company->owner_user_id ? (int) $company->owner_user_id : null;

        if ($previousRole === 'owner' && $nextRole !== 'owner' && $currentOwnerId === $userId) {
            $replacementOwnerId = DB::table('company_user')
                ->where('company_id', $company->getKey())
                ->where('role', 'owner')
                ->where('user_id', '!=', $userId)
                ->orderBy('created_at')
                ->value('user_id');

            if ($replacementOwnerId === null) {
                throw new \RuntimeException('Each company must retain at least one owner.');
            }

            $company->forceFill(['owner_user_id' => $replacementOwnerId])->save();
        }

        if ($nextRole === 'owner' && $currentOwnerId !== $userId) {
            $company->forceFill(['owner_user_id' => $userId])->save();
        }
    }

    private function reassignActiveMembership(User $member, int $removedCompanyId): void
    {
        if ((int) $member->company_id !== $removedCompanyId) {
            return;
        }

        $nextMembership = DB::table('company_user')
            ->where('user_id', $member->id)
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->first();

        if ($nextMembership === null) {
            $member->forceFill(['company_id' => null])->save();

            return;
        }

        DB::table('company_user')
            ->where('id', $nextMembership->id)
            ->update([
                'is_default' => true,
                'last_used_at' => $nextMembership->last_used_at ?? now(),
                'updated_at' => now(),
            ]);

        $member->forceFill([
            'company_id' => $nextMembership->company_id,
            'role' => $nextMembership->role,
        ])->save();
    }

    private function roleListAggregateExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "GROUP_CONCAT(DISTINCT cu_roles.role)",
            'pgsql' => "STRING_AGG(DISTINCT cu_roles.role, ',')",
            default => "GROUP_CONCAT(DISTINCT cu_roles.role ORDER BY cu_roles.role SEPARATOR ',')",
        };
    }
}

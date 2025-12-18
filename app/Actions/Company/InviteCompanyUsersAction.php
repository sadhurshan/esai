<?php

namespace App\Actions\Company;

use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationIssued;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteCompanyUsersAction
{
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator'];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<int, array<string, mixed>>  $invitations
     */
    public function execute(Company $company, User $inviter, array $invitations): Collection
    {
        $this->assertSupplierRoleEligibility($company, $invitations);

        return DB::transaction(function () use ($company, $inviter, $invitations): Collection {
            $created = collect();

            foreach ($invitations as $payload) {
                $email = strtolower(trim($payload['email']));
                $invitee = $this->resolveInviteeUser($company, $email, $payload['role']);

                CompanyInvitation::query()
                    ->where('company_id', $company->id)
                    ->where('email', $email)
                    ->whereNull('revoked_at')
                    ->whereNull('accepted_at')
                    ->update([
                        'revoked_at' => now(),
                        'revoked_by_user_id' => $inviter->id,
                    ]);

                $invitation = CompanyInvitation::query()->create([
                    'company_id' => $company->id,
                    'invited_by_user_id' => $inviter->id,
                    'email' => $email,
                    'role' => $payload['role'],
                    'message' => $payload['message'] ?? null,
                    'token' => $this->generateToken(),
                    'expires_at' => $this->determineExpiry($payload['expires_at'] ?? null),
                    'pending_user_id' => $invitee->id,
                ]);

                $invitation = $invitation->fresh(['company', 'invitedBy', 'pendingUser']);
                $created->push($invitation);

                $this->auditLogger->created($invitation);

                $invitee->notify(new CompanyInvitationIssued($invitation));
            }

            return $created;
        });
    }

    private function assertSupplierRoleEligibility(Company $company, array $invitations): void
    {
        if ($company->isSupplierApproved()) {
            return;
        }

        foreach ($invitations as $invitation) {
            $role = $invitation['role'] ?? null;

            if ($this->isSupplierRole($role)) {
                throw ValidationException::withMessages([
                    'invitations' => ['Supplier-role invitations require supplier approval.'],
                ]);
            }
        }
    }

    private function isSupplierRole(mixed $role): bool
    {
        return is_string($role) && in_array($role, self::SUPPLIER_ROLES, true);
    }

    private function generateToken(): string
    {
        return Str::random(64);
    }

    private function determineExpiry(?string $expiresAt): ?Carbon
    {
        if ($expiresAt === null || trim($expiresAt) === '') {
            return now()->addHours(48);
        }

        try {
            return Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return now()->addHours(48);
        }
    }

    private function resolveInviteeUser(Company $company, string $email, string $role): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user instanceof User) {
            if ($user->trashed()) {
                $user->restore();
            }

            return $user;
        }

        return User::create([
            'name' => $this->deriveInviteeName($email),
            'email' => $email,
            'password' => Str::random(40),
            'role' => $role,
            'status' => UserStatus::Pending->value,
            'company_id' => $company->id,
        ]);
    }

    private function deriveInviteeName(string $email): string
    {
        $local = Str::before($email, '@');
        $local = str_replace(['.', '_', '-'], ' ', $local);
        $local = preg_replace('/\s+/', ' ', $local ?? '') ?? '';

        $name = trim($local);

        if ($name === '') {
            return 'Pending User';
        }

        return ucwords($name);
    }
}

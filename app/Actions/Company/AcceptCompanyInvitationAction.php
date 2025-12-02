<?php

namespace App\Actions\Company;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AcceptCompanyInvitationAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function execute(CompanyInvitation $invitation, User $user): CompanyInvitation
    {
        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw new RuntimeException('Invitation email mismatch.');
        }

        if ($invitation->isRevoked()) {
            throw new RuntimeException('Invitation has been revoked.');
        }

        if ($invitation->isExpired()) {
            throw new RuntimeException('Invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            return $invitation;
        }

        return DB::transaction(function () use ($invitation, $user): CompanyInvitation {
            $shouldBeDefault = false;

            $existingMembership = DB::table('company_user')
                ->where('company_id', $invitation->company_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first(['id', 'is_default']);

            if ($existingMembership === null) {
                $shouldBeDefault = DB::table('company_user')
                    ->where('user_id', $user->id)
                    ->count() === 0;

                DB::table('company_user')->insert([
                    'company_id' => $invitation->company_id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                    'is_default' => $shouldBeDefault,
                    'last_used_at' => $shouldBeDefault ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $shouldBeDefault = (bool) $existingMembership->is_default;

                DB::table('company_user')
                    ->where('company_id', $invitation->company_id)
                    ->where('user_id', $user->id)
                    ->update([
                        'role' => $invitation->role,
                        'updated_at' => now(),
                    ]);
            }

            $isActiveCompany = (int) $user->company_id === (int) $invitation->company_id;

            if ($shouldBeDefault || $user->company_id === null) {
                $user->forceFill([
                    'company_id' => $invitation->company_id,
                    'role' => $invitation->role,
                ])->save();
            } elseif ($isActiveCompany && $user->role !== $invitation->role) {
                $user->forceFill(['role' => $invitation->role])->save();
            }

            $before = $invitation->getOriginal();

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
                'token' => Hash::make((string) $invitation->token),
            ]);

            $changes = $invitation->getDirty();
            $invitation->save();

            $this->auditLogger->updated($invitation, $before, $changes);

            return $invitation->fresh();
        });
    }
}

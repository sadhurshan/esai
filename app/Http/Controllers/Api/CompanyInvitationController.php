<?php

namespace App\Http\Controllers\Api;

use App\Actions\Company\AcceptCompanyInvitationAction;
use App\Actions\Company\InviteCompanyUsersAction;
use App\Enums\UserStatus;
use App\Http\Requests\Company\AcceptCompanyInvitationRequest;
use App\Http\Requests\Company\StoreCompanyInvitationRequest;
use App\Http\Resources\CompanyInvitationResource;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyInvitationController extends ApiController
{
    public function __construct(
        private readonly InviteCompanyUsersAction $inviteCompanyUsersAction,
        private readonly AcceptCompanyInvitationAction $acceptCompanyInvitationAction,
        private readonly AuditLogger $auditLogger,
    ) {
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
        $cursor = $request->query('cursor');

        $paginator = CompanyInvitation::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, CompanyInvitationResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Invitations retrieved.', $paginated['meta']);
    }

    public function store(StoreCompanyInvitationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $company = Company::query()->findOrFail($companyId);

        try {
            $invitations = $this->inviteCompanyUsersAction->execute($company, $user, $request->input('invitations', []));
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok([
            'items' => CompanyInvitationResource::collection($invitations),
        ], 'Invitations sent.');
    }

    public function destroy(Request $request, CompanyInvitation $invitation): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $companyId !== (int) $invitation->company_id) {
            return $this->fail('Forbidden.', 403);
        }

        if ($invitation->isAccepted()) {
            return $this->fail('Invitation already accepted.', 422);
        }

        $before = [
            'revoked_at' => optional($invitation->revoked_at)->toDateTimeString(),
            'revoked_by_user_id' => $invitation->revoked_by_user_id,
        ];

        $invitation->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->id,
        ])->save();

        $after = [
            'revoked_at' => optional($invitation->revoked_at)->toDateTimeString(),
            'revoked_by_user_id' => $invitation->revoked_by_user_id,
        ];

        $this->auditLogger->updated($invitation, $before, $after);

        return $this->ok(null, 'Invitation revoked.');
    }

    public function accept(AcceptCompanyInvitationRequest $request, string $token): JsonResponse
    {
        $payload = $request->validated();
        $email = strtolower($payload['email']);

        $invitation = CompanyInvitation::query()
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return $this->fail('Invitation not found.', 404);
        }

        if (strcasecmp($invitation->email, $email) !== 0) {
            return $this->fail('Invitation email mismatch.', 422, [
                'email' => ['Email does not match the invitation.'],
            ]);
        }

        if ($invitation->isRevoked()) {
            return $this->fail('Invitation has been revoked.', 410);
        }

        if ($invitation->isExpired()) {
            return $this->fail('Invitation has expired.', 422, [
                'token' => ['Invitation has expired.'],
            ]);
        }

        $user = $this->resolveRequestUser($request);

        if ($user instanceof User && strcasecmp($user->email, $email) !== 0) {
            return $this->fail('Signed-in user does not match the invited email.', 403);
        }

        if (! $user instanceof User) {
            $user = $this->resolvePendingInvitee($invitation, $email);

            if (! $user instanceof User) {
                return $this->fail('No pending user found for this invitation.', 404);
            }

            $statusValue = $user->status instanceof UserStatus
                ? $user->status->value
                : (string) $user->status;

            if ($statusValue !== UserStatus::Pending->value) {
                return $this->fail('Please sign in to accept this invitation.', 401);
            }

            if (! isset($payload['password']) || $payload['password'] === '') {
                return $this->fail('Password required to activate invitation.', 422, [
                    'password' => ['Password is required to activate your account.'],
                ]);
            }

            $user->forceFill([
                'name' => $payload['name'] ?? $user->name ?? $email,
                'password' => $payload['password'],
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ]);

            if ($user->company_id === null) {
                $user->company_id = $invitation->company_id;
            }

            if ($user->role === null || $user->role === '') {
                $user->role = $invitation->role;
            }

            $user->save();
        }

        try {
            $invitation = $this->acceptCompanyInvitationAction->execute($invitation, $user);
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        $this->auditLogger->custom($invitation, 'company_invitation_accepted', [
            'accepted_by' => $user->id,
        ]);

        return $this->ok(new CompanyInvitationResource($invitation), 'Invitation accepted.');
    }

    private function resolvePendingInvitee(CompanyInvitation $invitation, string $email): ?User
    {
        if ($invitation->pending_user_id !== null) {
            $user = User::withTrashed()->find($invitation->pending_user_id);

            if ($user instanceof User) {
                return $user;
            }
        }

        return User::withTrashed()->where('email', $email)->first();
    }
}

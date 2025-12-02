<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Settings\SwitchCompanyRequest;
use App\Http\Resources\UserCompanyResource;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserCompanyController extends ApiController
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

        $companies = $user->companies()
            ->withPivot(['role', 'is_default', 'last_used_at'])
            ->orderBy('companies.name')
            ->get();

        return $this->ok([
            'items' => UserCompanyResource::collection($companies),
        ], 'Companies retrieved.');
    }

    public function switch(SwitchCompanyRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = (int) $request->validated('company_id');

        $membershipExists = $user->companies()
            ->where('companies.id', $companyId)
            ->exists();

        if (! $membershipExists) {
            return $this->fail('Forbidden.', 403);
        }

        DB::transaction(function () use ($user, $companyId): void {
            $membership = DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first(['role']);

            if ($membership === null) {
                throw new RuntimeException('Membership could not be found for the requested company switch.');
            }

            DB::table('company_user')
                ->where('user_id', $user->id)
                ->update(['is_default' => false]);

            DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->update([
                    'is_default' => true,
                    'last_used_at' => now(),
                ]);

            $user->forceFill([
                'company_id' => $companyId,
                'role' => $membership->role ?? $user->role,
            ])->save();
        });

        $this->auditLogger->custom($user->fresh(), 'company_switch', [
            'company_id' => $companyId,
        ]);

        return $this->ok([
            'company_id' => $companyId,
        ], 'Active organization updated.');
    }
}

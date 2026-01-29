<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Company\ApproveCompanyAction;
use App\Actions\Company\FetchCompaniesHouseProfileAction;
use App\Actions\Company\RejectCompanyAction;
use App\Exceptions\CompaniesHouseLookupException;
use App\Enums\CompanyStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Company\RejectCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;
use App\Notifications\CompanyRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class CompanyApprovalController extends ApiController
{
    public function __construct(
        private readonly ApproveCompanyAction $approveCompanyAction,
        private readonly RejectCompanyAction $rejectCompanyAction,
        private readonly FetchCompaniesHouseProfileAction $companiesHouseProfileAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $this->fail('Forbidden.', 403);
        }

        $status = $request->query('status', CompanyStatus::PendingVerification->value);
        $isAllStatuses = $status === 'all';
        $validStatuses = array_map(static fn (CompanyStatus $value) => $value->value, CompanyStatus::cases());

        if (! $isAllStatuses && ! in_array($status, $validStatuses, true)) {
            return $this->fail('Invalid status filter.', 422);
        }

        $perPage = $this->perPage($request, 25, 100);

        $query = Company::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $isAllStatuses) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'))
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, CompanyResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Companies retrieved.', $paginated['meta']);
    }

    public function approve(Request $request, Company $company): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->role !== 'platform_super') {
            return $this->fail('Forbidden.', 403);
        }

        if (! in_array($company->status, [CompanyStatus::Pending, CompanyStatus::PendingVerification], true)) {
            return $this->fail('Only pending companies can be approved.', 422);
        }

        $company = $this->approveCompanyAction->execute($company)->fresh(['owner']);
        $this->notifyApproval($company);

        return $this->ok((new CompanyResource($company))->toArray($request), 'Company approved.');
    }

    public function reject(RejectCompanyRequest $request, Company $company): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->role !== 'platform_super') {
            return $this->fail('Forbidden.', 403);
        }

        if (! in_array($company->status, [CompanyStatus::Pending, CompanyStatus::PendingVerification], true)) {
            return $this->fail('Only pending companies can be rejected.', 422);
        }

        $reason = $request->validated('reason');
        $company = $this->rejectCompanyAction->execute($company, $reason)->fresh(['owner']);
        $this->notifyRejection($company, $reason);

        return $this->ok((new CompanyResource($company))->toArray($request), 'Company rejected.');
    }

    public function companiesHouseProfile(Request $request, Company $company): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $this->fail('Forbidden.', 403);
        }

        try {
            $profile = $this->companiesHouseProfileAction->execute($company);
        } catch (CompaniesHouseLookupException $exception) {
            return $this->fail($exception->getMessage(), $exception->status);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Unable to retrieve Companies House data right now. Please try again later.', 502);
        }

        return $this->ok([
            'profile' => $profile,
        ], 'Companies House profile retrieved.');
    }

    private function notifyApproval(Company $company): void
    {
        $owner = $company->owner;

        if ($owner !== null) {
            $owner->notify(new CompanyApproved($company, 'owner'));
        }

        $platformAdmins = $this->platformOperators($owner?->id);

        if ($platformAdmins->isNotEmpty()) {
            Notification::send($platformAdmins, new CompanyApproved($company, 'platform'));
        }
    }

    private function notifyRejection(Company $company, string $reason): void
    {
        $owner = $company->owner;

        if ($owner !== null) {
            $owner->notify(new CompanyRejected($company, $reason, 'owner'));
        }

        $platformAdmins = $this->platformOperators($owner?->id);

        if ($platformAdmins->isNotEmpty()) {
            Notification::send($platformAdmins, new CompanyRejected($company, $reason, 'platform'));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function platformOperators(?int $excludeUserId = null): Collection
    {
        return User::query()
            ->whereIn('role', ['platform_super', 'platform_support'])
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->get();
    }
}

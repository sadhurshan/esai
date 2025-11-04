<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Company\ApproveCompanyAction;
use App\Actions\Company\RejectCompanyAction;
use App\Enums\CompanyStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Company\RejectCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyApprovalController extends ApiController
{
    public function __construct(
        private readonly ApproveCompanyAction $approveCompanyAction,
        private readonly RejectCompanyAction $rejectCompanyAction,
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

        $status = $request->query('status', CompanyStatus::Pending->value);

        if (! in_array($status, array_map(static fn (CompanyStatus $value) => $value->value, CompanyStatus::cases()), true)) {
            return $this->fail('Invalid status filter.', 422);
        }

        $paginator = Company::query()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 25, 100))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, CompanyResource::class);

        return $this->ok([
            'items' => $items,
            'meta' => $meta,
        ]);
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

        if ($company->status !== CompanyStatus::Pending) {
            return $this->fail('Only pending companies can be approved.', 422);
        }

        $company = $this->approveCompanyAction->execute($company)->refresh();

        // TODO: trigger notification to company owner about approval.

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

        if ($company->status !== CompanyStatus::Pending) {
            return $this->fail('Only pending companies can be rejected.', 422);
        }

        $company = $this->rejectCompanyAction->execute($company, $request->validated('reason'))->refresh();

        // TODO: trigger notification to company owner about rejection.

        return $this->ok((new CompanyResource($company))->toArray($request), 'Company rejected.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Company\RegisterCompanyAction;
use App\Actions\Company\UpdateCompanyProfileAction;
use App\Http\Requests\Company\RegisterCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRegistrationController extends ApiController
{
    public function __construct(
        private readonly RegisterCompanyAction $registerCompanyAction,
        private readonly UpdateCompanyProfileAction $updateCompanyProfileAction,
    ) {}

    public function store(RegisterCompanyRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $this->registerCompanyAction->execute($request->validated(), $user);

        return $this->ok((new CompanyResource($company))->toArray($request), 'Company registration submitted.')
            ->setStatusCode(201);
    }

    public function show(Company $company, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $activeCompanyId = $this->resolveUserCompanyId($user);
        if ($activeCompanyId === null || $activeCompanyId !== $company->id) {
            return $this->fail('Forbidden.', 403);
        }

        return $this->ok((new CompanyResource($company))->toArray($request));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $activeCompanyId = $this->resolveUserCompanyId($user);
        if ($activeCompanyId === null || $activeCompanyId !== $company->id) {
            return $this->fail('Forbidden.', 403);
        }

        if (! in_array($user->role, ['buyer_admin', 'supplier_admin', 'platform_super'], true) && $user->id !== $company->owner_user_id) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = collect($request->validated())->only([
            'name',
            'registration_no',
            'tax_id',
            'country',
            'email_domain',
            'primary_contact_name',
            'primary_contact_email',
            'primary_contact_phone',
            'address',
            'phone',
            'website',
            'region',
        ])->toArray();

        $company = $this->updateCompanyProfileAction->execute($company, $payload);

        return $this->ok((new CompanyResource($company))->toArray($request), 'Company profile updated.');
    }
}

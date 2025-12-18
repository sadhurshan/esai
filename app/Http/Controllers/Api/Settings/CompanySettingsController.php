<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Settings\UpdateCompanySettingsRequest;
use App\Http\Resources\Settings\CompanySettingsResource;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Services\CompanyBrandingService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanySettingsController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyBrandingService $brandingService,
    )
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company === null) {
            return $this->fail('Company context required.', 403);
        }

        $profile = $this->getProfile($user->company);

        return $this->ok((new CompanySettingsResource($profile))->toArray($request));
    }

    public function update(UpdateCompanySettingsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company === null) {
            return $this->fail('Company context required.', 403);
        }

        $company = $user->company;

        $profile = CompanyProfile::query()->firstOrNew(['company_id' => $company->id]);
        $before = $profile->exists ? $profile->getOriginal() : [];
        $payload = $request->payload();
        $logoFile = $request->file('logo');
        $markFile = $request->file('mark');

        if ($logoFile) {
            $payload['logo_url'] = $this->brandingService->storeLogo($profile, $logoFile);
        } elseif ($request->exists('logo_url') && ($payload['logo_url'] ?? null) === null) {
            $this->brandingService->deleteLogo($profile);
        }

        if ($markFile) {
            $payload['mark_url'] = $this->brandingService->storeMark($profile, $markFile);
        } elseif ($request->exists('mark_url') && ($payload['mark_url'] ?? null) === null) {
            $this->brandingService->deleteMark($profile);
        }

        $profile->fill($payload);
        $dirty = ! $profile->exists || $profile->isDirty();
        $profile->save();

        if ($profile->wasRecentlyCreated) {
            $this->auditLogger->created($profile, $profile->toArray());
        } elseif ($dirty) {
            $this->auditLogger->updated($profile, $before, $profile->toArray());
        }

        return $this->ok((new CompanySettingsResource($profile))->toArray($request), 'Company settings updated.');
    }

    private function getProfile(Company $company): CompanyProfile
    {
        $profile = $company->profile;

        if ($profile instanceof CompanyProfile) {
            return $profile;
        }

        $profile = CompanyProfile::firstOrCreate(
            ['company_id' => $company->id],
            [
                'legal_name' => $company->name,
                'display_name' => $company->name,
                'tax_id' => $company->tax_id,
                'registration_number' => $company->registration_no,
                'emails' => array_filter([$company->primary_contact_email]),
                'phones' => array_filter([$company->primary_contact_phone]),
            ]
        );

        $company->setRelation('profile', $profile);

        return $profile;
    }
}

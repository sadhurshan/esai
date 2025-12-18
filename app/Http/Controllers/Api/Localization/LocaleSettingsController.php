<?php

namespace App\Http\Controllers\Api\Localization;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Localization\UpdateLocaleSettingsRequest;
use App\Http\Resources\Localization\CompanyLocaleSettingResource;
use App\Models\Company;
use App\Models\CompanyLocaleSetting;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocaleSettingsController extends ApiController
{
    public function __construct(private readonly LocaleService $localeService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $company = Company::query()->with('localeSetting')->find($companyId);

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $setting = $this->localeService->getForCompany($company);

        return $this->ok((new CompanyLocaleSettingResource($setting))->toArray($request));
    }

    public function update(UpdateLocaleSettingsRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $company = Company::query()->with('localeSetting')->find($companyId);

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $payload = $request->payload();

        $setting = CompanyLocaleSetting::query()->firstOrNew(['company_id' => $companyId]);
        $before = $setting->exists ? $setting->getOriginal() : [];

        $setting->fill($payload);
        $dirty = $setting->isDirty();
        $setting->save();

        $resource = new CompanyLocaleSettingResource($setting);

        if (! $setting->wasRecentlyCreated && ! $dirty) {
            return $this->ok($resource->toArray($request), 'Locale settings unchanged.');
        }

        if ($setting->wasRecentlyCreated) {
            $this->localeService->recordCreation($setting);
        } elseif ($dirty) {
            $this->localeService->recordUpdate($setting, $before, $setting->toArray());
        }

        return $this->ok($resource->toArray($request), 'Locale settings updated.');
    }
}

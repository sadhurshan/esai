<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyLocaleSetting;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class LocaleService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function getForCompany(Company $company): CompanyLocaleSetting
    {
        $setting = $company->localeSetting;

        if ($setting instanceof CompanyLocaleSetting) {
            return $setting;
        }

        $setting = CompanyContext::bypass(fn () => CompanyLocaleSetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'locale' => 'en-US',
                'timezone' => 'UTC',
                'number_format' => '1,234.56',
                'date_format' => 'YYYY-MM-DD',
                'first_day_of_week' => 1,
                'weekend_days' => [6, 0],
                'currency_primary' => 'USD',
                'currency_display_fx' => false,
                'uom_base' => 'EA',
                'uom_maps' => [],
            ]
        ));

        $company->setRelation('localeSetting', $setting);

        return $setting;
    }

    public function apply(Request $request, Company $company): CompanyLocaleSetting
    {
        $setting = $this->getForCompany($company);

        $locale = $setting->locale ?: 'en-US';
        $timezone = $setting->timezone ?: 'UTC';

        $frameworkLocale = $this->resolveFrameworkLocale($locale);

        App::setLocale($frameworkLocale);
        Carbon::setLocale($frameworkLocale);
        Config::set('app.timezone', $timezone);
        date_default_timezone_set($timezone);

        $request->attributes->set('company_locale_setting', $setting);
        App::instance('company.locale_setting', $setting);

        return $setting;
    }

    public function recordUpdate(CompanyLocaleSetting $setting, array $before, array $after): void
    {
        $this->auditLogger->updated($setting, $before, $after);
    }

    public function recordCreation(CompanyLocaleSetting $setting): void
    {
        $this->auditLogger->created($setting, $setting->toArray());
    }

    private function resolveFrameworkLocale(?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return 'en';
        }

        $normalized = str_replace('_', '-', $locale);
        $segment = strtok($normalized, '-') ?: $normalized;

        return strtolower($segment) ?: 'en';
    }
}

<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyLocaleSetting;
use App\Support\Audit\AuditLogger;
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

        $setting = CompanyLocaleSetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'locale' => 'en',
                'timezone' => 'UTC',
                'number_format' => 'system',
                'date_format' => 'system',
                'first_day_of_week' => 1,
                'weekend_days' => [6, 0],
            ]
        );

        $company->setRelation('localeSetting', $setting);

        return $setting;
    }

    public function apply(Request $request, Company $company): CompanyLocaleSetting
    {
        $setting = $this->getForCompany($company);

        $locale = $setting->locale ?: 'en';
        $timezone = $setting->timezone ?: 'UTC';

        App::setLocale($locale);
        Carbon::setLocale($locale);
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
}

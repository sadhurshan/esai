<?php

namespace App\Http\Controllers\Api;

use App\Enums\MoneyRoundRule;
use App\Enums\TaxRegime;
use App\Http\Requests\UpdateMoneySettingsRequest;
use App\Http\Resources\MoneySettingsResource;
use App\Models\CompanyMoneySetting;
use App\Models\Currency;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoneySettingsController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $settings = $company->moneySetting;

        if ($settings === null) {
            $defaultCurrency = $this->defaultCurrencyCode();
            $currency = Currency::query()->where('code', $defaultCurrency)->first();

            $settings = new CompanyMoneySetting([
                'company_id' => $company->id,
                'base_currency' => $defaultCurrency,
                'pricing_currency' => $defaultCurrency,
                'fx_source' => 'manual',
                'price_round_rule' => MoneyRoundRule::HalfUp->value,
                'tax_regime' => TaxRegime::Exclusive->value,
                'defaults_meta' => [],
            ]);

            $settings->setRelation('baseCurrency', $currency);
            $settings->setRelation('pricingCurrency', $currency);
        } else {
            $settings->loadMissing(['baseCurrency', 'pricingCurrency']);
        }

        return $this->ok((new MoneySettingsResource($settings))->toArray($request));
    }

    public function update(UpdateMoneySettingsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $payload = $request->payload();

        $setting = CompanyMoneySetting::query()->firstOrNew(['company_id' => $company->id]);
        $setting->fill($payload);

        $wasExisting = $setting->exists;
        $before = $wasExisting ? $setting->getOriginal() : [];
        $dirty = ! $setting->exists || $setting->isDirty();

        $setting->save();
        $setting->load(['baseCurrency', 'pricingCurrency']);

        if (! $wasExisting) {
            $this->auditLogger->created($setting, $setting->toArray());
        } elseif ($dirty) {
            $this->auditLogger->updated($setting, $before, $setting->toArray());
        }

        return $this->ok((new MoneySettingsResource($setting))->toArray($request), 'Money settings updated.');
    }

    private function defaultCurrencyCode(): string
    {
        $preferred = 'USD';

        if (Currency::query()->where('code', $preferred)->exists()) {
            return $preferred;
        }

        return (string) Currency::query()->orderBy('code')->value('code') ?: 'USD';
    }
}

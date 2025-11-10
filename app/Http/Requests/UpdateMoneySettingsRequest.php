<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMoneySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->company_id === null) {
            return false;
        }

        return in_array($user->role, ['owner', 'buyer_admin'], true);
    }

    public function rules(): array
    {
        return [
            'base_currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'pricing_currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'fx_source' => ['required', Rule::in(['manual', 'provider'])],
            'price_round_rule' => ['required', Rule::in(['bankers', 'half_up'])],
            'tax_regime' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'defaults_meta' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_currency' => $this->normalizeCurrency($this->input('base_currency')),
            'pricing_currency' => $this->normalizeCurrency($this->input('pricing_currency')),
        ]);
    }

    public function payload(): array
    {
        $base = $this->input('base_currency');
        $pricing = $this->input('pricing_currency') ?: $base;

        return [
            'base_currency' => $base,
            'pricing_currency' => $pricing,
            'fx_source' => $this->input('fx_source'),
            'price_round_rule' => $this->input('price_round_rule'),
            'tax_regime' => $this->input('tax_regime'),
            'defaults_meta' => $this->input('defaults_meta') ?? [],
        ];
    }

    private function normalizeCurrency(?string $value): ?string
    {
        return $value === null ? null : strtoupper($value);
    }
}

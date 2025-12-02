<?php

namespace App\Http\Requests;

use App\Support\Permissions\PermissionRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertFxRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return app(PermissionRegistry::class)
            ->userHasAny($user, ['billing.write'], (int) $user->company_id);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Billing permissions required.');
    }

    public function rules(): array
    {
        return [
            'rates' => ['required', 'array', 'min:1', 'max:100'],
            'rates.*.base_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'rates.*.quote_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'rates.*.rate' => ['required', 'numeric', 'gt:0'],
            'rates.*.as_of' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rates = collect($this->input('rates', []))
            ->map(function ($rate) {
                if (! is_array($rate)) {
                    return $rate;
                }

                return array_merge($rate, [
                    'base_code' => $this->normalizeCurrency($rate['base_code'] ?? null),
                    'quote_code' => $this->normalizeCurrency($rate['quote_code'] ?? null),
                ]);
            })
            ->all();

        $this->merge(['rates' => $rates]);
    }

    /**
     * @return array<int, array{base_code:string, quote_code:string, rate:string, as_of:string}>
     */
    public function payload(): array
    {
        return collect($this->input('rates', []))
            ->map(function (array $rate) {
                return [
                    'base_code' => $this->normalizeCurrency($rate['base_code'] ?? ''),
                    'quote_code' => $this->normalizeCurrency($rate['quote_code'] ?? ''),
                    'rate' => number_format((float) ($rate['rate'] ?? 0), 8, '.', ''),
                    'as_of' => $rate['as_of'],
                ];
            })
            ->all();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('rates', []) as $index => $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                $base = strtoupper((string) ($rate['base_code'] ?? ''));
                $quote = strtoupper((string) ($rate['quote_code'] ?? ''));

                if ($base !== '' && $base === $quote) {
                    $validator->errors()->add("rates.{$index}.quote_code", 'Quote currency must differ from base currency.');
                }
            }
        });
    }

    private function normalizeCurrency(?string $value): ?string
    {
        return $value === null ? null : strtoupper($value);
    }
}

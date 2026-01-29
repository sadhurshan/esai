<?php

namespace App\Services\CompaniesHouse;

use App\Exceptions\CompaniesHouseLookupException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompaniesHouseClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
    ) {}

    public function isEnabled(): bool
    {
        return filled($this->config('api_key'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchCompanyProfile(string $companyNumber): ?array
    {
        if (! $this->isEnabled()) {
            throw new CompaniesHouseLookupException('Companies House API credentials are not configured.', 503);
        }

        $normalized = $this->normalizeCompanyNumber($companyNumber);

        if ($normalized === '') {
            throw new CompaniesHouseLookupException('A valid company registration number is required.');
        }

        $cacheTtl = (int) $this->config('cache_ttl', 0);
        $cacheKey = $this->cacheKey($normalized);

        if ($cacheTtl > 0) {
            return $this->cache->remember($cacheKey, $cacheTtl, fn (): ?array => $this->requestProfile($normalized));
        }

        return $this->requestProfile($normalized);
    }

    private function requestProfile(string $companyNumber): ?array
    {
        $response = $this->http
            ->baseUrl($this->config('base_url', 'https://api.company-information.service.gov.uk'))
            ->timeout((int) $this->config('timeout', 10))
            ->retry(2, 200)
            ->acceptJson()
            ->withBasicAuth((string) $this->config('api_key'), '')
            ->get("/company/{$companyNumber}");

        if ($response->successful()) {
            $payload = $response->json() ?? [];

            return $this->transformProfile($payload);
        }

        if ($response->status() === 404) {
            return null;
        }

        $this->logFailure($response, $companyNumber);

        throw new CompaniesHouseLookupException('Companies House lookup failed. Please try again later.', 502);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function transformProfile(array $payload): array
    {
        return [
            'company_name' => $payload['company_name'] ?? null,
            'company_number' => $payload['company_number'] ?? null,
            'company_status' => $payload['company_status'] ?? null,
            'type' => $payload['type'] ?? null,
            'jurisdiction' => $payload['jurisdiction'] ?? null,
            'sic_codes' => $this->transformSicCodes($payload['sic_codes'] ?? []),
            'date_of_creation' => $payload['date_of_creation'] ?? null,
            'undeliverable_registered_office_address' => $payload['undeliverable_registered_office_address'] ?? null,
            'has_been_liquidated' => $payload['has_been_liquidated'] ?? null,
            'can_file' => $payload['can_file'] ?? null,
            'registered_office_address' => $this->transformAddress($payload['registered_office_address'] ?? null),
            'accounts' => $this->transformAccounts($payload['accounts'] ?? null),
            'confirmation_statement' => $this->transformConfirmationStatement($payload['confirmation_statement'] ?? null),
            'previous_company_names' => $this->transformPreviousNames($payload['previous_company_names'] ?? []),
            'retrieved_at' => now()->toIso8601String(),
            'raw' => $payload,
        ];
    }

    /**
     * @param array<int, string>|string|null $codes
     * @return array<int, string>
     */
    private function transformSicCodes(array|string|null $codes): array
    {
        if ($codes === null) {
            return [];
        }

        $values = is_array($codes) ? $codes : [$codes];

        return array_values(array_filter(array_map(
            static fn ($code): string => (string) $code,
            $values,
        )));
    }

    /**
     * @param array<string, mixed>|null $address
     * @return array<string, mixed>|null
     */
    private function transformAddress(?array $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return array_filter([
            'care_of' => $address['care_of'] ?? null,
            'po_box' => $address['po_box'] ?? null,
            'address_line_1' => $address['address_line_1'] ?? null,
            'address_line_2' => $address['address_line_2'] ?? null,
            'locality' => $address['locality'] ?? null,
            'region' => $address['region'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed>|null $accounts
     * @return array<string, mixed>|null
     */
    private function transformAccounts(?array $accounts): ?array
    {
        if ($accounts === null) {
            return null;
        }

        return array_filter([
            'accounting_reference_date' => $accounts['accounting_reference_date'] ?? null,
            'next_due' => $accounts['next_due'] ?? null,
            'last_accounts' => $accounts['last_accounts'] ?? null,
            'overdue' => $accounts['overdue'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed>|null $statement
     * @return array<string, mixed>|null
     */
    private function transformConfirmationStatement(?array $statement): ?array
    {
        if ($statement === null) {
            return null;
        }

        return array_filter([
            'next_due' => $statement['next_due'] ?? null,
            'next_made_up_to' => $statement['next_made_up_to'] ?? null,
            'overdue' => $statement['overdue'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<int, array<string, mixed>> $previousNames
     * @return array<int, array<string, mixed>>
     */
    private function transformPreviousNames(array $previousNames): array
    {
        return array_values(array_filter(array_map(
            static function ($entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $normalized = array_filter([
                    'name' => $entry['name'] ?? null,
                    'ceased_on' => $entry['ceased_on'] ?? null,
                    'effective_from' => $entry['effective_from'] ?? null,
                ], static fn ($value) => $value !== null && $value !== '');

                return $normalized === [] ? null : $normalized;
            },
            $previousNames,
        )));
    }

    private function normalizeCompanyNumber(string $companyNumber): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($companyNumber)) ?? '');
    }

    private function cacheKey(string $companyNumber): string
    {
        return sprintf('companies_house:profile:%s', $companyNumber);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("services.companies_house.{$key}", $default);
    }

    private function logFailure(Response $response, string $companyNumber): void
    {
        Log::warning('Companies House API request failed', [
            'company_number' => $companyNumber,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);
    }
}

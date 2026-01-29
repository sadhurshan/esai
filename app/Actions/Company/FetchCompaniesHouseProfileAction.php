<?php

namespace App\Actions\Company;

use App\Exceptions\CompaniesHouseLookupException;
use App\Models\Company;
use App\Services\CompaniesHouse\CompaniesHouseClient;
use Illuminate\Support\Str;

class FetchCompaniesHouseProfileAction
{
    private const UK_IDENTIFIERS = [
        'uk',
        'gb',
        'gbr',
        'united kingdom',
        'great britain',
        'england',
        'scotland',
        'wales',
        'northern ireland',
    ];

    public function __construct(private readonly CompaniesHouseClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Company $company): array
    {
        if (! $this->isUkCompany($company->country)) {
            throw new CompaniesHouseLookupException('Companies House data is only available for United Kingdom companies.');
        }

        if (! filled($company->registration_no)) {
            throw new CompaniesHouseLookupException('The company record is missing a registration number.');
        }

        $profile = $this->client->fetchCompanyProfile((string) $company->registration_no);

        if ($profile === null) {
            throw new CompaniesHouseLookupException('No Companies House record was found for this registration number.', 404);
        }

        return $profile;
    }

    private function isUkCompany(?string $country): bool
    {
        if (! filled($country)) {
            return false;
        }

        $normalized = Str::lower(trim((string) $country));

        foreach (self::UK_IDENTIFIERS as $identifier) {
            if ($normalized === $identifier) {
                return true;
            }
        }

        return str_contains($normalized, 'united kingdom') || str_contains($normalized, 'great britain');
    }
}

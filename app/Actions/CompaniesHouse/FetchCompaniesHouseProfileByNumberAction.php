<?php

namespace App\Actions\CompaniesHouse;

use App\Exceptions\CompaniesHouseLookupException;
use App\Services\CompaniesHouse\CompaniesHouseClient;

class FetchCompaniesHouseProfileByNumberAction
{
    public function __construct(private readonly CompaniesHouseClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $companyNumber): array
    {
        $profile = $this->client->fetchCompanyProfile($companyNumber);

        if ($profile === null) {
            throw new CompaniesHouseLookupException('No Companies House record was found for this registration number.', 404);
        }

        return $profile;
    }
}

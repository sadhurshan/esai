<?php

namespace App\Actions\CompaniesHouse;

use App\Services\CompaniesHouse\CompaniesHouseClient;

class SearchCompaniesHouseAction
{
    public function __construct(private readonly CompaniesHouseClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $query, ?int $limit = null): array
    {
        return $this->client->searchCompanies($query, $limit ?? 8);
    }
}

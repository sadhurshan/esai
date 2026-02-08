<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\CompaniesHouse\FetchCompaniesHouseProfileByNumberAction;
use App\Actions\CompaniesHouse\SearchCompaniesHouseAction;
use App\Exceptions\CompaniesHouseLookupException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CompaniesHouse\CompaniesHouseProfileRequest;
use App\Http\Requests\CompaniesHouse\CompaniesHouseSearchRequest;
use Illuminate\Http\JsonResponse;

class CompaniesHouseLookupController extends ApiController
{
    public function __construct(
        private readonly SearchCompaniesHouseAction $searchCompaniesHouseAction,
        private readonly FetchCompaniesHouseProfileByNumberAction $fetchCompaniesHouseProfileByNumberAction,
    ) {}

    public function search(CompaniesHouseSearchRequest $request): JsonResponse
    {
        try {
            $results = $this->searchCompaniesHouseAction->execute(
                (string) $request->validated('q'),
                $request->validated('limit'),
            );
        } catch (CompaniesHouseLookupException $exception) {
            return $this->fail($exception->getMessage(), $exception->status);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Unable to search Companies House right now. Please try again later.', 502);
        }

        return $this->ok($results, 'Companies House search results retrieved.');
    }

    public function profile(CompaniesHouseProfileRequest $request): JsonResponse
    {
        try {
            $profile = $this->fetchCompaniesHouseProfileByNumberAction->execute(
                (string) $request->validated('company_number'),
            );
        } catch (CompaniesHouseLookupException $exception) {
            return $this->fail($exception->getMessage(), $exception->status);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Unable to retrieve Companies House data right now. Please try again later.', 502);
        }

        return $this->ok([
            'profile' => $profile,
        ], 'Companies House profile retrieved.');
    }
}

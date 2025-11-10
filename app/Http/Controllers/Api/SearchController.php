<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\SearchResultResource;
use App\Models\Company;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends ApiController
{
    public function __construct(private readonly GlobalSearchService $service)
    {
    }

    public function index(SearchRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $query = (string) $request->input('q');
        $types = $request->input('types', []);
        $filters = array_filter([
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'tags' => $request->input('tags'),
            'category' => $request->input('category'),
            'visibility' => $request->input('visibility'),
        ], static fn ($value) => $value !== null && $value !== '');

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $result = $this->service->search(
            is_array($types) ? $types : [$types],
            $query,
            $filters,
            $company,
            $page,
            $perPage
        );

        $items = collect($result['items'])
            ->map(static fn (array $item) => (new SearchResultResource($item))->toArray($request))
            ->all();

        return $this->ok(
            [
                'items' => $items,
                'meta' => $result['meta'],
            ],
            'Search results retrieved.'
        );
    }
}

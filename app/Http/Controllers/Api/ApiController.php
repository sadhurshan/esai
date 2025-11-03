<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithEnvelope;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    use RespondsWithEnvelope;

    /**
     * @template TResource of object
     *
     * @param  class-string<TResource>  $resourceClass
     * @return array{items: array<int, mixed>, meta: array<string, int>}
     */
    protected function paginate(LengthAwarePaginator $paginator, Request $request, string $resourceClass): array
    {
        $items = collect($paginator->items())
            ->map(static fn ($item) => (new $resourceClass($item))->toArray($request))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    protected function perPage(Request $request, int $default = 10, int $max = 50): int
    {
        $perPage = (int) $request->query('per_page', $default);
        if ($perPage < 1) {
            $perPage = $default;
        }

        return min($perPage, $max);
    }

    protected function sortDirection(Request $request, string $default = 'desc'): string
    {
        $direction = strtolower((string) $request->query('sort_direction', $default));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
    }

    protected function resolveRequestUser(Request $request): ?User
    {
        if (config('auth.guards.sanctum') !== null) {
            try {
                $sanctumUser = $request->user('sanctum');
            } catch (\InvalidArgumentException) {
                $sanctumUser = null;
            }

            if ($sanctumUser instanceof User) {
                return $sanctumUser;
            }
        }

        $defaultUser = $request->user();

        return $defaultUser instanceof User ? $defaultUser : null;
    }
}

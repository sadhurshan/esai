<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithEnvelope;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\RequestCompanyContextResolver;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

abstract class ApiController extends Controller
{
    use RespondsWithEnvelope;

    /**
     * @template TResource of object
     *
     * @param  class-string<TResource>  $resourceClass
     * @return array{items: array<int, mixed>, meta: array{data?: array<string, mixed>, envelope?: array<string, mixed>}}
     */
    protected function paginate(CursorPaginator|LengthAwarePaginator $paginator, Request $request, string $resourceClass): array
    {
        $items = collect($paginator->items())
            ->map(static fn ($item) => (new $resourceClass($item))->toArray($request))
            ->values()
            ->all();

        if ($paginator instanceof CursorPaginator) {
            $next = $paginator->nextCursor()?->encode();
            $prev = $paginator->previousCursor()?->encode();

            return [
                'items' => $items,
                'meta' => [
                    'data' => [
                        'next_cursor' => $next,
                        'prev_cursor' => $prev,
                        'per_page' => $paginator->perPage(),
                    ],
                    'envelope' => [
                        'cursor' => [
                            'next_cursor' => $next,
                            'prev_cursor' => $prev,
                            'has_next' => $next !== null,
                            'has_prev' => $prev !== null,
                        ],
                    ],
                ],
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'data' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
                'envelope' => [
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
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
        return RequestCompanyContextResolver::resolveRequestUser($request);
    }

    protected function resolveUserCompanyId(User $user): ?int
    {
        return RequestCompanyContextResolver::resolveUserCompanyId($user);
    }

    /**
     * @return array{user:User, companyId:int}|JsonResponse
     */
    protected function requireCompanyContext(Request $request): array|JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Active company context required.', 422, [
                'code' => 'company_context_missing',
            ]);
        }

        if ($user->company_id === null) {
            $user->company_id = $companyId;
        }

        CompanyContext::set($companyId);

        $request->setUserResolver(static fn () => $user);

        return ['user' => $user, 'companyId' => $companyId];
    }

    protected function authorizeDenied(?User $user, string $ability, mixed $arguments): bool
    {
        if ($user === null) {
            return true;
        }

        return Gate::forUser($user)->denies($ability, $arguments);
    }
}

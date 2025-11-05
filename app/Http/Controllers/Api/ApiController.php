<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithEnvelope;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

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

    protected function resolveUserCompanyId(User $user): ?int
    {
        if ($user->company_id !== null) {
            return (int) $user->company_id;
        }

        $companyId = DB::table('company_user')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->value('company_id');

        if ($companyId) {
            return (int) $companyId;
        }

        $ownedCompanyId = DB::table('companies')
            ->where('owner_user_id', $user->id)
            ->orderByDesc('created_at')
            ->value('id');

        if ($ownedCompanyId) {
            return (int) $ownedCompanyId;
        }

        $supplierCompanyId = DB::table('suppliers')
            ->where('email', $user->email)
            ->orderByDesc('created_at')
            ->value('company_id');

        return $supplierCompanyId ? (int) $supplierCompanyId : null;
    }

    protected function authorizeDenied(?User $user, string $ability, mixed $arguments): bool
    {
        if ($user === null) {
            return true;
        }

        return Gate::forUser($user)->denies($ability, $arguments);
    }
}

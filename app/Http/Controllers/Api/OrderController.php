<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->resolveRequestUser($request);
            if ($user === null) {
                return $this->fail('Authentication required.', 401);
            }

            if ($this->authorizeDenied($user, 'viewAny', Order::class)) {
                return $this->fail('Forbidden', 403);
            }

            $companyId = $this->resolveUserCompanyId($user);
            if ($companyId === null) {
                return $this->fail('Company context required.', 403);
            }

            $tab = $request->query('tab');
            $query = $this->scopedOrderQuery($companyId, $tab);

            if ($status = $request->query('status')) {
                $allowedStatuses = ['pending', 'confirmed', 'in_production', 'delivered', 'cancelled'];
                if (! in_array($status, $allowedStatuses, true)) {
                    return $this->fail('Invalid status filter', 422);
                }

                $query->where('status', $status);
            }

            if ($dateFrom = $request->query('date_from')) {
                try {
                    $query->whereDate('ordered_at', '>=', Carbon::parse($dateFrom));
                } catch (\Throwable $exception) {
                    return $this->fail('Invalid date_from filter', 422);
                }
            }

            if ($dateTo = $request->query('date_to')) {
                try {
                    $query->whereDate('ordered_at', '<=', Carbon::parse($dateTo));
                } catch (\Throwable $exception) {
                    return $this->fail('Invalid date_to filter', 422);
                }
            }

            $direction = $this->sortDirection($request);
            $query
                ->orderBy('ordered_at', $direction)
                ->orderBy('id', $direction);

            $paginator = $query
                ->cursorPaginate($this->perPage($request))
                ->withQueryString();

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, OrderResource::class);

            return $this->ok([
                'items' => $items,
            ], null, $meta);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $orderId, Request $request): JsonResponse
    {
        try {
            $user = $this->resolveRequestUser($request);
            if ($user === null) {
                return $this->fail('Authentication required.', 401);
            }

            $companyId = $this->resolveUserCompanyId($user);
            if ($companyId === null) {
                return $this->fail('Company context required.', 403);
            }

            $order = $this->scopedOrderQuery($companyId, $request->query('tab'))
                ->whereKey($orderId)
                ->first();

            if (! $order) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'view', $order)) {
                return $this->fail('Forbidden', 403);
            }

            return $this->ok((new OrderResource($order))->toArray($request));
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    private function scopedOrderQuery(int $companyId, ?string $tab): Builder
    {
        return Order::query()->where(function (Builder $builder) use ($companyId, $tab): void {
            if ($tab === 'received') {
                $builder->where('supplier_company_id', $companyId);
            } elseif ($tab === 'requested') {
                $builder->where('company_id', $companyId);
            } else {
                $builder
                    ->where('company_id', $companyId)
                    ->orWhere('supplier_company_id', $companyId);
            }
        });
    }
}

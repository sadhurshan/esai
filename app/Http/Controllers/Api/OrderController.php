<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::query();

            if ($tab = $request->query('tab')) {
                if ($tab === 'requested') {
                    $query->where('party_type', 'supplier');
                } elseif ($tab === 'received') {
                    $query->where('party_type', 'customer');
                }
            }

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
            $query->orderBy('ordered_at', $direction);

            $paginator = $query->paginate($this->perPage($request))->withQueryString();

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, OrderResource::class);

            return $this->ok([
                'items' => $items,
                'meta' => $meta,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $orderId, Request $request): JsonResponse
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                return $this->fail('Not found', 404);
            }

            return $this->ok((new OrderResource($order))->toArray($request));
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }
}

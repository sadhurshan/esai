<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RFQStoreRequest;
use App\Http\Requests\RFQUpdateRequest;
use App\Http\Resources\RFQResource;
use App\Models\RFQ;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RFQController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        try {
            $query = RFQ::query()
                ->where('company_id', $companyId);

            $statusFilter = $this->normalizeStatus($request->query('status'));

            if ($statusFilter === null) {
                $tab = $request->query('tab');
                $query = $this->mapTabFilters($query, $tab);
            } else {
                $statusValues = $this->resolveStatusFilter($statusFilter);
                if ($statusValues !== null) {
                    $query->whereIn('status', $statusValues);
                }
            }

            if ($search = $request->query('q')) {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('number', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%");
                });
            }

            $dateFrom = $this->normalizeDate($request->query('date_from'));
            $dateTo = $this->normalizeDate($request->query('date_to'));

            if ($dateFrom || $dateTo) {
                $query->where(function (Builder $builder) use ($dateFrom, $dateTo): void {
                    $builder->where(function (Builder $sent) use ($dateFrom, $dateTo): void {
                        $sent->whereNotNull('sent_at');

                        if ($dateFrom) {
                            $sent->whereDate('sent_at', '>=', $dateFrom);
                        }

                        if ($dateTo) {
                            $sent->whereDate('sent_at', '<=', $dateTo);
                        }
                    })->orWhere(function (Builder $drafts) use ($dateFrom, $dateTo): void {
                        $drafts->whereNull('sent_at');

                        if ($dateFrom) {
                            $drafts->whereDate('created_at', '>=', $dateFrom);
                        }

                        if ($dateTo) {
                            $drafts->whereDate('created_at', '<=', $dateTo);
                        }
                    });
                });
            }

            $sort = $request->query('sort', 'sent_at');
            $allowedSorts = ['sent_at', 'deadline_at'];
            if (! in_array($sort, $allowedSorts, true)) {
                $sort = 'sent_at';
            }

            $query->orderBy($sort, $this->sortDirection($request));

            $paginator = $query->paginate($this->perPage($request))->withQueryString();

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RFQResource::class);

            return $this->ok([
                'items' => $items,
                'meta' => $meta,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $rfqId, Request $request): JsonResponse
    {
        try {
            $rfq = RFQ::with(['items', 'quotes.supplier', 'quotes.items', 'quotes.documents'])->find($rfqId);

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            return $this->ok((new RFQResource($rfq))->toArray($request));
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function store(RFQStoreRequest $request): JsonResponse
    {
        $payload = [];
        try {
            $payload = $request->validated();
            $items = $payload['items'];
            unset($payload['items']);

            $user = $request->user();
            if ($user === null || $user->company_id === null) {
                return $this->fail('Unauthorized', 403);
            }

            $payload['number'] = $this->generateNumber();
            $payload['status'] = $payload['status'] ?? 'awaiting';
            $payload['is_open_bidding'] = (bool) ($payload['is_open_bidding'] ?? false);
            $payload['company_id'] = $user->company_id;
            $payload['created_by'] = $user->id;

            if ($request->hasFile('cad')) {
                $payload['cad_path'] = $request->file('cad')->store('cad');
            }

            unset($payload['cad']);

            $rfq = DB::transaction(function () use ($payload, $items): RFQ {
                $rfq = RFQ::create($payload);

                $lineNo = 1;
                foreach ($items as $item) {
                    RfqItem::create([
                        'rfq_id' => $rfq->id,
                        'line_no' => $lineNo++,
                        'part_name' => $item['part_name'],
                        'spec' => $item['spec'] ?? null,
                        'quantity' => $item['quantity'],
                        'uom' => $item['uom'] ?? 'pcs',
                        'target_price' => $item['target_price'] ?? null,
                    ]);
                }

                return $rfq;
            });

            $rfq->load('items');

            return $this->ok((new RFQResource($rfq))->toArray($request), 'RFQ created')->setStatusCode(201);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function update(string $rfqId, RFQUpdateRequest $request): JsonResponse
    {
        try {
            $rfq = RFQ::find($rfqId);

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $data = $request->validated();

            if ($request->hasFile('cad')) {
                if ($rfq->cad_path) {
                    Storage::delete($rfq->cad_path);
                }

                $data['cad_path'] = $request->file('cad')->store('cad');
            }

            unset($data['cad']);

            if (! array_key_exists('is_open_bidding', $data)) {
                // Keep existing value if not provided
            } else {
                $data['is_open_bidding'] = (bool) $data['is_open_bidding'];
            }

            $rfq->fill($data);
            $rfq->save();

            return $this->ok((new RFQResource($rfq->fresh('quotes')))->toArray($request), 'RFQ updated');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function destroy(string $rfqId): JsonResponse
    {
        try {
            $rfq = RFQ::find($rfqId);

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($rfq->cad_path) {
                Storage::disk('public')->delete($rfq->cad_path);
                Storage::delete($rfq->cad_path);
            }

            $rfq->delete();

            return $this->ok(null, 'RFQ deleted');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    private function mapTabFilters(Builder $query, ?string $tab): Builder
    {
        $normalized = $tab ? strtolower($tab) : null;

        return match ($normalized) {
            'open' => $query
                ->where('is_open_bidding', true)
                ->where('status', 'open'),
            'received', 'sent' => $this->applySentConstraints($query),
            default => $query,
        };
    }

    private function normalizeStatus(mixed $status): ?string
    {
        if (! is_string($status)) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return $normalized === '' || $normalized === 'all' ? null : $normalized;
    }

    /**
     * @return list<string>|null
     */
    private function resolveStatusFilter(string $status): ?array
    {
        return match ($status) {
            'draft' => ['awaiting'],
            'open' => ['open'],
            'closed' => ['closed', 'cancelled'],
            'awarded' => ['awarded'],
            default => null,
        };
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = sprintf('%05d', random_int(0, 99999));
        } while (RFQ::where('number', $number)->exists());

        return $number;
    }

    private function applySentConstraints(Builder $query): Builder
    {
        return $query
            ->whereIn('status', ['awaiting', 'awarded', 'closed', 'cancelled'])
            ->whereNotNull('sent_at');
    }
}

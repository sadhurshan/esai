<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RFQStoreRequest;
use App\Http\Requests\RFQUpdateRequest;
use App\Http\Resources\RFQResource;
use App\Models\RFQ;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RFQController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = RFQ::query();

            $tab = $request->query('tab');
            $query = $this->mapTabFilters($query, $tab);

            if ($search = $request->query('q')) {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('number', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%");
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
            $rfq = RFQ::with(['quotes.supplier'])->find($rfqId);

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
        try {
            $data = $request->validated();
            $data['number'] = $this->generateNumber();
            $data['status'] = $data['status'] ?? 'awaiting';
            $data['is_open_bidding'] = (bool) ($data['is_open_bidding'] ?? false);

            if ($request->hasFile('cad')) {
                $data['cad_path'] = $request->file('cad')->store('cad');
            }

            unset($data['cad']);

            $rfq = RFQ::create($data);

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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RfqLineStoreRequest;
use App\Http\Requests\RfqLineUpdateRequest;
use App\Http\Resources\RfqItemResource;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RfqLineController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RfqVersionService $rfqVersionService,
    ) {
    }

    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || (int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden', 403);
        }

        $lines = $rfq->items()
            ->orderBy('line_no')
            ->get();

        return $this->ok([
            'items' => RfqItemResource::collection($lines)->resolve(),
        ]);
    }

    public function store(RFQ $rfq, RfqLineStoreRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || (int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'update', $rfq)) {
            return $this->fail('Forbidden', 403);
        }

        $data = $request->validated();

        /** @var RfqItem $line */
        $line = DB::transaction(function () use ($rfq, $user, $data) {
            $nextLineNo = ((int) $rfq->items()->max('line_no')) + 1;

            $attributes = $this->mapLineAttributes($data);
            $attributes['line_no'] = max(1, $nextLineNo);
            $attributes['company_id'] = $rfq->company_id;
            $attributes['created_by'] = $user->id;
            $attributes['updated_by'] = $user->id;

            /** @var RfqItem $item */
            $item = $rfq->items()->create($attributes);

            return $item->fresh();
        });

        $this->rfqVersionService->bump($rfq, null, 'rfq_line_added', [
            'line_id' => $line->id,
            'line_no' => $line->line_no,
        ]);

        $this->auditLogger->created($line);

        return $this->ok((new RfqItemResource($line))->toArray($request), 'RFQ line created')
            ->setStatusCode(201);
    }

    public function update(RFQ $rfq, string $lineId, RfqLineUpdateRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || (int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'update', $rfq)) {
            return $this->fail('Forbidden', 403);
        }

        /** @var RfqItem|null $line */
        $line = $rfq->items()
            ->whereKey($lineId)
            ->first();

        if (! $line) {
            return $this->fail('Not found', 404);
        }

        $data = $request->validated();
        $attributes = $this->mapLineAttributes($data, $line->meta ?? []);

        if ($attributes === []) {
            return $this->ok((new RfqItemResource($line))->toArray($request), 'RFQ line updated');
        }

        $line->fill($attributes);
        $dirtyKeys = array_keys($line->getDirty());

        if ($dirtyKeys === []) {
            $line->refresh();

            return $this->ok((new RfqItemResource($line))->toArray($request), 'RFQ line updated');
        }

        $before = Arr::only($line->getOriginal(), $dirtyKeys);

        $line->updated_by = $user->id;
        $line->save();

        $line->refresh();

        $this->auditLogger->updated(
            $line,
            $before,
            Arr::only($line->getAttributes(), $dirtyKeys)
        );

        $this->rfqVersionService->bump($rfq, null, 'rfq_line_updated', [
            'line_id' => $line->id,
            'line_no' => $line->line_no,
            'fields' => $dirtyKeys,
        ]);

        return $this->ok((new RfqItemResource($line))->toArray($request), 'RFQ line updated');
    }

    public function destroy(RFQ $rfq, string $lineId, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || (int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'update', $rfq)) {
            return $this->fail('Forbidden', 403);
        }

        /** @var RfqItem|null $line */
        $line = $rfq->items()
            ->whereKey($lineId)
            ->first();

        if (! $line) {
            return $this->fail('Not found', 404);
        }

        $before = $line->getAttributes();
        $lineIdValue = $line->id;
        $lineNo = $line->line_no;

        $line->delete();

        $this->auditLogger->deleted($line, $before);

        $this->rfqVersionService->bump($rfq, null, 'rfq_line_removed', [
            'line_id' => $lineIdValue,
            'line_no' => $lineNo,
        ]);

        return $this->ok(null, 'RFQ line deleted');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existingMeta
     * @return array<string, mixed>
     */
    private function mapLineAttributes(array $data, array $existingMeta = []): array
    {
        $attributes = [];

        if (array_key_exists('part_number', $data)) {
            $attributes['part_number'] = $data['part_number'];
            $attributes['part_name'] = $data['part_number'];
        }

        if (array_key_exists('spec', $data)) {
            $attributes['spec'] = $data['spec'];
            $attributes['description'] = $data['spec'];
        }

        foreach (['method', 'material', 'tolerance', 'finish', 'uom', 'target_price', 'cad_doc_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        if (array_key_exists('qty', $data)) {
            $attributes['qty'] = $data['qty'];
            $attributes['quantity'] = $data['qty'];
        }

        if (array_key_exists('notes', $data)) {
            $meta = $existingMeta;

            if ($data['notes'] === null || $data['notes'] === '') {
                unset($meta['notes']);
            } else {
                $meta['notes'] = $data['notes'];
            }

            $attributes['meta'] = $meta;
        }

        return $attributes;
    }
}

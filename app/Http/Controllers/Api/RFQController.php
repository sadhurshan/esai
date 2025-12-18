<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfqs\SyncRfqCadDocumentAction;
use App\Http\Requests\RFQCancelRequest;
use App\Http\Requests\RFQCloseRequest;
use App\Http\Requests\RFQPublishRequest;
use App\Http\Requests\RFQStoreRequest;
use App\Http\Requests\RFQUpdateRequest;
use App\Http\Requests\Rfq\ExtendRfqDeadlineRequest;
use App\Http\Resources\RFQResource;
use App\Http\Resources\RfqDeadlineExtensionResource;
use App\Models\Company;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RfqDeadlineExtensionService;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RFQController extends ApiController
{
    private const AUDIT_FIELDS = [
        'title',
        'method',
        'material',
        'tolerance',
        'finish',
        'quantity_total',
        'delivery_location',
        'incoterm',
        'currency',
        'notes',
        'open_bidding',
        'publish_at',
        'due_at',
        'close_at',
        'status',
        'rfq_version',
        'meta',
    ];

    private const STRUCTURAL_FIELDS = [
        'title',
        'method',
        'material',
        'tolerance',
        'finish',
        'quantity_total',
        'delivery_location',
        'incoterm',
        'currency',
        'notes',
        'open_bidding',
        'publish_at',
        'due_at',
        'close_at',
        'cad_document_id',
        'meta',
    ];

    private const SUPPLIER_VISIBLE_STATUSES = [
        RFQ::STATUS_OPEN,
        RFQ::STATUS_CLOSED,
        RFQ::STATUS_AWARDED,
    ];

    private const BUYER_NOTIFICATION_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];

    private const SUPPLIER_NOTIFICATION_ROLES = ['supplier_admin', 'supplier_estimator'];

    public function __construct(
        private readonly SyncRfqCadDocumentAction $cadDocuments,
        private readonly AuditLogger $auditLogger,
        private readonly RfqVersionService $rfqVersionService,
        private readonly NotificationService $notifications,
        private readonly RfqDeadlineExtensionService $deadlineExtensions,
    ) {
    }
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
            $isSupplier = $this->isSupplierUser($user);

            $responseBuilder = function () use ($request, $user, $companyId, $isSupplier) {
                return $this->buildRfqIndexResponse($request, $user, $companyId, $isSupplier);
            };

            return $isSupplier
                ? CompanyContext::bypass($responseBuilder)
                : $responseBuilder();
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function show(string $rfqId, Request $request): JsonResponse
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
            $rfq = CompanyContext::bypass(static function () use ($rfqId) {
                return RFQ::query()
                    ->with(['items', 'quotes.supplier', 'quotes.items', 'quotes.documents', 'cadDocument'])
                    ->whereKey($rfqId)
                    ->first();
            });

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if (! $this->userCanViewRfq($user, $rfq, $companyId)) {
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
            [$meta, $metaChanged] = $this->extractRfqMeta($payload);
            if ($metaChanged) {
                $payload['meta'] = $meta;
            }
            $items = $payload['items'];
            unset($payload['items']);

            $user = $this->resolveRequestUser($request);

            if ($user === null) {
                return $this->fail('Authentication required.', 401);
            }

            $companyId = $this->resolveUserCompanyId($user);

            if ($companyId === null) {
                return $this->fail('Company context required.', 403);
            }

            if ($this->authorizeDenied($user, 'create', RFQ::class)) {
                return $this->fail('Forbidden', 403);
            }

            $payload['quantity_total'] = $payload['quantity_total'] ?? $this->sumLineQuantities($items);
            $payload['method'] = $payload['method'] ?? $this->resolveLineValue($items, 'method');
            $payload['material'] = $payload['material'] ?? $this->resolveLineValue($items, 'material');
            $payload['tolerance'] = $payload['tolerance'] ?? $this->resolveLineValue($items, 'tolerance');
            $payload['finish'] = $payload['finish'] ?? $this->resolveLineValue($items, 'finish');
            $payload['number'] = $this->generateNumber();
            $payload['status'] = RFQ::STATUS_DRAFT;
            $payload['open_bidding'] = (bool) ($payload['open_bidding'] ?? false);
            $payload['close_at'] = $payload['close_at'] ?? ($payload['due_at'] ?? null);
            $payload['company_id'] = $companyId;
            $payload['created_by'] = $user->id;

            /** @var UploadedFile|null $cadFile */
            $cadFile = $request->file('cad');
            unset($payload['cad']);

            $rfq = DB::transaction(function () use ($payload, $items, $cadFile, $user): RFQ {
                $rfq = RFQ::create($payload);

                $lineNo = 1;
                foreach ($items as $item) {
                        RfqItem::create([
                            'rfq_id' => $rfq->id,
                            'company_id' => $rfq->company_id,
                            'created_by' => $user->id,
                            'line_no' => $lineNo++,
                            'part_number' => $item['part_number'],
                            'description' => $item['description'] ?? $item['spec'] ?? null,
                            'method' => $item['method'],
                            'material' => $item['material'],
                            'tolerance' => $item['tolerance'] ?? null,
                            'finish' => $item['finish'] ?? null,
                            'qty' => $item['qty'],
                            'uom' => $item['uom'] ?? 'pcs',
                            'target_price' => $item['target_price'] ?? null,
                            'cad_doc_id' => $item['cad_doc_id'] ?? null,
                            'specs_json' => $item['specs_json'] ?? null,
                        ]);
                }

                if ($cadFile instanceof UploadedFile) {
                    $this->cadDocuments->attach($rfq, $cadFile, $user);
                }

                return $rfq;
            });

            $rfq->load(['items', 'cadDocument']);

            $this->auditLogger->created($rfq);

            return $this->ok((new RFQResource($rfq))->toArray($request), 'RFQ created')->setStatusCode(201);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function publish(string $rfqId, RFQPublishRequest $request): JsonResponse
    {
        try {
            $rfq = CompanyContext::bypass(static fn () => RFQ::query()->whereKey($rfqId)->first());

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $user = $this->resolveRequestUser($request);

            if ($user === null) {
                return $this->fail('Authentication required.', 401);
            }

            $companyId = $this->resolveUserCompanyId($user);

            if ($companyId === null || (int) $rfq->company_id !== (int) $companyId) {
                return $this->fail('Forbidden', 403);
            }

            if ($this->authorizeDenied($user, 'publish', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            if ($rfq->status !== RFQ::STATUS_DRAFT) {
                return $this->fail('Only draft RFQs can be published.', 422, [
                    'status' => ['RFQ is currently '.$rfq->status.'.'],
                ]);
            }

            $data = $request->validated();
            $message = isset($data['message']) ? trim((string) $data['message']) : null;
            $message = $message === '' ? null : $message;

            $shouldNotifySuppliers = array_key_exists('notify_suppliers', $data)
                ? (bool) $data['notify_suppliers']
                : true;

            $dueAt = Carbon::parse($data['due_at']);
            $publishAt = isset($data['publish_at']) && $data['publish_at'] !== null
                ? Carbon::parse($data['publish_at'])
                : Carbon::now();

            if ($publishAt->greaterThan($dueAt)) {
                $publishAt = $dueAt->copy();
            }

            $before = Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS);

            $rfq->fill([
                'due_at' => $dueAt,
                'close_at' => $dueAt,
                'publish_at' => $publishAt,
                'status' => RFQ::STATUS_OPEN,
            ]);
            $rfq->save();

            $this->rfqVersionService->bump($rfq, null, 'rfq_published', [
                'publish_at' => optional($rfq->publish_at)->toIso8601String(),
                'due_at' => optional($rfq->due_at)->toIso8601String(),
            ]);

            $this->auditLogger->updated($rfq, $before, Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS));

            $company = Company::query()->find($companyId);

            if ($company && (int) $company->id === (int) $rfq->company_id) {
                $company->increment('rfqs_monthly_used');
            }

            $rfq->load(['items', 'company', 'creator', 'invitations.supplier.company']);

            $this->notifyRfqPublished($rfq, $user, $shouldNotifySuppliers, $message);

            return $this->ok((new RFQResource($rfq))->toArray($request), 'RFQ published');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function update(string $rfqId, RFQUpdateRequest $request): JsonResponse
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
            $rfq = RFQ::query()
                ->where('company_id', $companyId)
                ->whereKey($rfqId)
                ->first();
            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'update', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            if ($rfq->status !== RFQ::STATUS_DRAFT) {
                return $this->fail('Only draft RFQs can be edited directly.', 422, [
                    'status' => ['RFQs must be amended after publishing.'],
                ]);
            }

            $data = $request->validated();
            $currentMeta = $rfq->meta === null
                ? null
                : (is_array($rfq->meta) ? $rfq->meta : (array) $rfq->meta);
            [$meta, $metaChanged] = $this->extractRfqMeta($data, $currentMeta);
            if ($metaChanged) {
                $data['meta'] = $meta;
            }
            $before = Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS);

            /** @var UploadedFile|null $cadFile */
            $cadFile = $request->file('cad');
            unset($data['cad']);

            if (array_key_exists('open_bidding', $data)) {
                $data['open_bidding'] = (bool) $data['open_bidding'];
            }

            $rfq->fill($data);
            $dirtyKeys = array_keys($rfq->getDirty());
            $rfq->save();

            if ($cadFile instanceof UploadedFile) {
                $this->cadDocuments->attach($rfq, $cadFile, $user);
            }

            $structuralDirty = $this->structuralDirtyFields($dirtyKeys);

            if ($structuralDirty !== []) {
                $this->rfqVersionService->bump($rfq, null, 'rfq_updated', [
                    'fields' => $structuralDirty,
                ]);
            }

            $this->auditLogger->updated($rfq, $before, Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS));

            return $this->ok((new RFQResource($rfq->fresh(['quotes', 'cadDocument'])))->toArray($request), 'RFQ updated');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function extendDeadline(string $rfqId, ExtendRfqDeadlineRequest $request): JsonResponse
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
            $rfq = RFQ::query()
                ->where('company_id', $companyId)
                ->whereKey($rfqId)
                ->first();

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'extendDeadline', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            $payload = $request->validated();
            $newDueAt = Carbon::parse($payload['new_due_at']);
            $reason = trim($payload['reason']);
            $notifySuppliers = array_key_exists('notify_suppliers', $payload)
                ? (bool) $payload['notify_suppliers']
                : true;

            try {
                $extension = $this->deadlineExtensions->extend($rfq, $user, $newDueAt, $reason, $notifySuppliers);
            } catch (ValidationException $exception) {
                return $this->fail($exception->getMessage(), 422, $exception->errors());
            }

            return $this->ok([
                'extension' => (new RfqDeadlineExtensionResource($extension))->toArray($request),
                'rfq' => (new RFQResource($rfq->fresh()))->toArray($request),
            ], 'RFQ deadline extended');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function destroy(string $rfqId, Request $request): JsonResponse
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
            $rfq = RFQ::query()
                ->where('company_id', $companyId)
                ->whereKey($rfqId)
                ->first();

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'delete', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            $before = $rfq->toArray();

            $this->cadDocuments->detach($rfq);
            $rfq->delete();

            $this->auditLogger->deleted($rfq, $before);

            return $this->ok(null, 'RFQ deleted');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function cancel(string $rfqId, RFQCancelRequest $request): JsonResponse
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
            $rfq = RFQ::query()
                ->where('company_id', $companyId)
                ->whereKey($rfqId)
                ->first();

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'update', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            if ($rfq->status === RFQ::STATUS_CANCELLED) {
                return $this->fail('RFQ already cancelled.', 422, [
                    'status' => ['RFQ already cancelled.'],
                ]);
            }

            if ($rfq->status === RFQ::STATUS_AWARDED) {
                return $this->fail('Awarded RFQs cannot be cancelled.', 422, [
                    'status' => ['Awarded RFQs cannot be cancelled.'],
                ]);
            }

            if ($rfq->status === RFQ::STATUS_CLOSED) {
                return $this->fail('Closed RFQs cannot be cancelled.', 422, [
                    'status' => ['Closed RFQs cannot be cancelled.'],
                ]);
            }

            $data = $request->validated();
            $reason = array_key_exists('reason', $data) ? $this->normalizeReason($data['reason']) : null;
            $cancelledAt = isset($data['cancelled_at']) && $data['cancelled_at'] !== null
                ? Carbon::parse($data['cancelled_at'])
                : Carbon::now();

            $before = Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS);

            $meta = $rfq->meta ?? [];
            if ($reason === null) {
                unset($meta['cancellation_reason']);
            } else {
                $meta['cancellation_reason'] = $reason;
            }

            $rfq->fill([
                'status' => RFQ::STATUS_CANCELLED,
                'close_at' => $cancelledAt,
                'meta' => $meta,
            ]);
            $rfq->save();

            $this->rfqVersionService->bump($rfq, null, 'rfq_cancelled', array_filter([
                'reason' => $reason,
            ]));

            $this->auditLogger->updated($rfq, $before, Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS));

            $rfq->load(['company', 'creator', 'invitations.supplier.company']);

            $this->notifyRfqCancelled($rfq, $user, $reason);

            return $this->ok((new RFQResource($rfq))->toArray($request), 'RFQ cancelled');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function close(string $rfqId, RFQCloseRequest $request): JsonResponse
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
            $rfq = RFQ::query()
                ->where('company_id', $companyId)
                ->whereKey($rfqId)
                ->first();

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            if ($this->authorizeDenied($user, 'update', $rfq)) {
                return $this->fail('Forbidden', 403);
            }

            if ($rfq->status !== RFQ::STATUS_OPEN) {
                return $this->fail('Only open RFQs can be closed.', 422, [
                    'status' => ['Only open RFQs can be closed.'],
                ]);
            }

            $data = $request->validated();
            $reason = array_key_exists('reason', $data) ? $this->normalizeReason($data['reason']) : null;
            $closedAt = isset($data['closed_at']) && $data['closed_at'] !== null
                ? Carbon::parse($data['closed_at'])
                : Carbon::now();

            $before = Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS);

            $meta = $rfq->meta ?? [];
            if ($reason === null) {
                unset($meta['closure_reason']);
            } else {
                $meta['closure_reason'] = $reason;
            }

            $rfq->fill([
                'status' => RFQ::STATUS_CLOSED,
                'close_at' => $closedAt,
                'meta' => $meta,
            ]);
            $rfq->save();

            $this->rfqVersionService->bump($rfq, null, 'rfq_closed', array_filter([
                'reason' => $reason,
            ]));

            $this->auditLogger->updated($rfq, $before, Arr::only($rfq->getAttributes(), self::AUDIT_FIELDS));

            $rfq->load(['company', 'creator', 'invitations.supplier.company']);

            $this->notifyRfqClosed($rfq, $user, $reason);

            return $this->ok((new RFQResource($rfq))->toArray($request), 'RFQ closed');
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    private function notifyRfqPublished(RFQ $rfq, User $publisher, bool $notifySuppliers, ?string $message): void
    {
        $meta = [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->number,
            'rfq_title' => $rfq->title,
            'company_id' => $rfq->company_id,
            'company_name' => $rfq->company?->name,
            'owner_id' => $rfq->creator?->id,
            'owner_name' => $rfq->creator?->name,
            'deadline_at' => optional($rfq->due_at)->toIso8601String(),
            'message' => $message,
        ];

        $reference = $rfq->number ?? $rfq->title ?? '#'.$rfq->id;
        $buyerRecipients = $this->buyerRecipients($rfq)
            ->reject(static fn (User $recipient): bool => (int) $recipient->id === (int) $publisher->id);

        if ($buyerRecipients->isNotEmpty()) {
            $buyerTitle = sprintf('RFQ %s published', $reference);
            $buyerBody = $message ?? sprintf('RFQ %s is now open for supplier responses.', $reference);

            $this->notifications->send(
                $buyerRecipients,
                'rfq_published',
                $buyerTitle,
                $buyerBody,
                RFQ::class,
                $rfq->id,
                array_merge($meta, ['audience' => 'buyer'])
            );
        }

        if (! $notifySuppliers) {
            return;
        }

        $supplierRecipients = $this->supplierRecipients($rfq);

        if ($supplierRecipients->isEmpty()) {
            return;
        }

        $deadline = $rfq->due_at?->toDayDateTimeString();
        $supplierBody = $message ?? sprintf(
            'You have been invited to quote on RFQ %s%s.',
            $reference,
            $deadline ? ' due '.$deadline : ''
        );

        $this->notifications->send(
            $supplierRecipients,
            'rfq_published',
            sprintf('New RFQ %s available', $reference),
            $supplierBody,
            RFQ::class,
            $rfq->id,
            array_merge($meta, ['audience' => 'supplier'])
        );
    }

    private function buildRfqIndexResponse(Request $request, User $user, int $companyId, bool $isSupplier): JsonResponse
    {
        $query = RFQ::query();

        if ($isSupplier) {
            $supplierIds = $this->supplierIdsForUser($user);

            $query->where(function (Builder $builder) use ($supplierIds): void {
                $builder->where('open_bidding', true);

                if ($supplierIds !== []) {
                    $builder->orWhereHas('invitations', function (Builder $invitationQuery) use ($supplierIds): void {
                        $invitationQuery->whereIn('supplier_id', $supplierIds);
                    });
                }
            });

            $query->whereIn('status', $this->supplierStatusFilters($this->extractStatusFilters($request->query('status'))));
        } else {
            $query->where('company_id', $companyId);
            $statusFilters = $this->extractStatusFilters($request->query('status'));

            if ($statusFilters === null) {
                $tab = $request->query('tab');
                $query = $this->mapTabFilters($query, $tab);
            } else {
                $query->whereIn('status', $statusFilters);
            }
        }

        $openBidding = $this->normalizeBoolean($request->query('open_bidding'));

        if ($openBidding !== null) {
            $query->where('open_bidding', $openBidding);
        }

        $methodFilters = $this->extractMethodFilters($request->query('method'));

        if ($methodFilters !== []) {
            $query->whereIn('method', $methodFilters);
        }

        $materialFilters = $this->extractScalarFilters($request->query('material'));

        if ($materialFilters !== []) {
            $query->whereIn('material', $materialFilters);
        }

        $dueFrom = $this->normalizeDate($request->query('due_from'));
        $dueTo = $this->normalizeDate($request->query('due_to'));

        if ($dueFrom || $dueTo) {
            $query->whereNotNull('due_at');

            if ($dueFrom) {
                $query->whereDate('due_at', '>=', $dueFrom);
            }

            if ($dueTo) {
                $query->whereDate('due_at', '<=', $dueTo);
            }
        }

        $search = $this->resolveSearchTerm($request->query('search') ?? $request->query('q'));

        if ($search !== null) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('material', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'due_at');
        $allowedSorts = ['due_at', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'due_at';
        }

        $direction = $this->sortDirection($request);

        $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction);

        $cursorName = 'cursor';
        $cursor = $request->query($cursorName);

        $paginator = $query
            ->cursorPaginate($this->perPage($request, 25, 100), ['*'], $cursorName, $cursor);

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RFQResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    private function notifyRfqCancelled(RFQ $rfq, User $actor, ?string $reason): void
    {
        $reference = $rfq->number ?? '#'.$rfq->id;
        $meta = [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->number,
            'rfq_title' => $rfq->title,
            'status' => $rfq->status,
            'closed_at' => optional($rfq->close_at)->toIso8601String(),
            'reason' => $reason,
        ];

        $buyerRecipients = $this->buyerRecipients($rfq)
            ->reject(static fn (User $recipient): bool => (int) $recipient->id === (int) $actor->id);

        if ($buyerRecipients->isNotEmpty()) {
            $this->notifications->send(
                $buyerRecipients,
                'rfq_cancelled',
                sprintf('RFQ %s cancelled', $reference),
                $reason ? sprintf('RFQ %s was cancelled: %s', $reference, $reason) : sprintf('RFQ %s was cancelled.', $reference),
                RFQ::class,
                $rfq->id,
                array_merge($meta, ['audience' => 'buyer'])
            );
        }

        $supplierRecipients = $this->supplierRecipients($rfq);

        if ($supplierRecipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $supplierRecipients,
            'rfq_cancelled',
            sprintf('RFQ %s cancelled', $reference),
            $reason ? sprintf('RFQ %s was cancelled by the buyer: %s', $reference, $reason) : sprintf('RFQ %s was cancelled by the buyer.', $reference),
            RFQ::class,
            $rfq->id,
            array_merge($meta, ['audience' => 'supplier'])
        );
    }

    private function notifyRfqClosed(RFQ $rfq, User $actor, ?string $reason): void
    {
        $reference = $rfq->number ?? '#'.$rfq->id;
        $meta = [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->number,
            'rfq_title' => $rfq->title,
            'status' => $rfq->status,
            'closed_at' => optional($rfq->close_at)->toIso8601String(),
            'reason' => $reason,
        ];

        $buyerRecipients = $this->buyerRecipients($rfq)
            ->reject(static fn (User $recipient): bool => (int) $recipient->id === (int) $actor->id);

        if ($buyerRecipients->isNotEmpty()) {
            $this->notifications->send(
                $buyerRecipients,
                'rfq_closed',
                sprintf('RFQ %s closed', $reference),
                $reason ? sprintf('RFQ %s was closed: %s', $reference, $reason) : sprintf('RFQ %s is now closed.', $reference),
                RFQ::class,
                $rfq->id,
                array_merge($meta, ['audience' => 'buyer'])
            );
        }

        $supplierRecipients = $this->supplierRecipients($rfq);

        if ($supplierRecipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $supplierRecipients,
            'rfq_closed',
            sprintf('RFQ %s closed', $reference),
            $reason ? sprintf('RFQ %s has closed for responses: %s', $reference, $reason) : sprintf('RFQ %s has closed for responses.', $reference),
            RFQ::class,
            $rfq->id,
            array_merge($meta, ['audience' => 'supplier'])
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function buyerRecipients(RFQ $rfq): Collection
    {
        return User::query()
            ->where('company_id', $rfq->company_id)
            ->whereIn('role', [...self::BUYER_NOTIFICATION_ROLES, 'platform_super', 'platform_support'])
            ->get();
    }

    private function normalizeReason(mixed $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $trimmed = trim($reason);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, 500, '');
    }

    /**
     * @return Collection<int, User>
     */
    private function supplierRecipients(RFQ $rfq): Collection
    {
        $rfq->loadMissing('invitations.supplier');

        $invitedCompanyIds = $rfq->invitations
            ->pluck('supplier.company_id')
            ->filter()
            ->map(static fn ($companyId) => (int) $companyId)
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->whereIn('role', [...self::SUPPLIER_NOTIFICATION_ROLES, 'platform_super', 'platform_support']);

        if ((bool) $rfq->open_bidding) {
            $query->where(function (Builder $builder) use ($invitedCompanyIds): void {
                $listedSuppliers = Company::query()->listedSuppliers()->select('id');

                if ($invitedCompanyIds !== []) {
                    $builder->whereIn('company_id', $invitedCompanyIds)
                        ->orWhereIn('company_id', $listedSuppliers);

                    return;
                }

                $builder->whereIn('company_id', $listedSuppliers);
            });

            return $query->get();
        }

        if ($invitedCompanyIds === []) {
            return collect();
        }

        return $query
            ->whereIn('company_id', $invitedCompanyIds)
            ->get();
    }

    private function mapTabFilters(Builder $query, ?string $tab): Builder
    {
        $normalized = $tab ? strtolower($tab) : null;

        return match ($normalized) {
            'open' => $query
                ->where('open_bidding', true)
                ->where('status', RFQ::STATUS_OPEN),
            'received', 'sent' => $this->applySentConstraints($query),
            default => $query,
        };
    }

    /**
     * @param  list<string>|null  $statusFilters
     * @return list<string>
     */
    private function supplierStatusFilters(?array $statusFilters): array
    {
        if ($statusFilters === null) {
            return [RFQ::STATUS_OPEN];
        }

        $filtered = array_values(array_intersect($statusFilters, self::SUPPLIER_VISIBLE_STATUSES));

        return $filtered === [] ? [RFQ::STATUS_OPEN] : $filtered;
    }

    /**
     * @return list<string>|null
     */
    private function extractStatusFilters(mixed $status): ?array
    {
        $values = $this->parseCsvValues($status, lowercase: true);

        if ($values === []) {
            return null;
        }

        $valid = array_values(array_intersect($values, RFQ::STATUSES));

        return $valid === [] ? null : $valid;
    }

    /**
     * @return list<string>
     */
    private function extractMethodFilters(mixed $methods): array
    {
        $values = $this->parseCsvValues($methods, lowercase: true);

        if ($values === []) {
            return [];
        }

        return array_values(array_intersect($values, RFQ::METHODS));
    }

    /**
     * @return list<string>
     */
    private function extractScalarFilters(mixed $value): array
    {
        return $this->parseCsvValues($value, lowercase: false);
    }

    private function isSupplierUser(User $user): bool
    {
        $role = $user->role;

        if (! is_string($role) || $role === '') {
            return false;
        }

        return Str::startsWith($role, 'supplier_');
    }

    /**
     * @return list<int>
     */
    private function supplierIdsForUser(User $user): array
    {
        $companyId = $user->company_id;

        if ($companyId === null) {
            return [];
        }

        return Supplier::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    private function userCanViewRfq(User $user, RFQ $rfq, ?int $companyId): bool
    {
        if ($companyId !== null && (int) $rfq->company_id === (int) $companyId) {
            return true;
        }

        if ($this->isSupplierUser($user)) {
            if ((bool) $rfq->open_bidding) {
                return true;
            }

            $supplierIds = $this->supplierIdsForUser($user);

            if ($supplierIds === []) {
                return false;
            }

            $rfq->loadMissing('invitations');

            return $rfq->invitations
                ->pluck('supplier_id')
                ->map(static fn ($id) => (int) $id)
                ->contains(static fn (int $invitedSupplierId): bool => in_array($invitedSupplierId, $supplierIds, true));
        }

        return $user->isPlatformAdmin();
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
            ->whereIn('status', [RFQ::STATUS_DRAFT, RFQ::STATUS_AWARDED, RFQ::STATUS_CLOSED, RFQ::STATUS_CANCELLED])
            ->whereNotNull('publish_at');
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    private function resolveSearchTerm(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, 120, '');
    }

    /**
     * @return list<string>
     */
    private function parseCsvValues(mixed $value, bool $lowercase): array
    {
        if ($value === null) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);
        $results = [];

        foreach ($items as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }

            $trimmed = trim((string) $item);

            if ($trimmed === '') {
                continue;
            }

            $results[] = $lowercase ? strtolower($trimmed) : $trimmed;
        }

        return array_values(array_unique($results));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function sumLineQuantities(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $total += (int) ($item['qty'] ?? $item['quantity'] ?? 0);
        }

        return max($total, 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function resolveLineValue(array $items, string $key): ?string
    {
        foreach ($items as $item) {
            $value = $item[$key] ?? null;

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $currentMeta
     * @return array{0: array<string, mixed>|null, 1: bool}
     */
    private function extractRfqMeta(array &$payload, ?array $currentMeta = null): array
    {
        $meta = is_array($currentMeta) ? $currentMeta : [];
        $changed = false;

        if (array_key_exists('meta', $payload)) {
            $incoming = $payload['meta'];
            unset($payload['meta']);

            if (is_array($incoming)) {
                $meta = array_merge($meta, $incoming);
                $changed = true;
            }
        }

        if (array_key_exists('payment_terms', $payload)) {
            $value = $this->normalizeMetaString($payload['payment_terms']);
            unset($payload['payment_terms']);

            $existing = $meta['payment_terms'] ?? null;

            if ($value === null) {
                if (array_key_exists('payment_terms', $meta)) {
                    unset($meta['payment_terms']);
                    $changed = true;
                }
            } elseif ($existing !== $value) {
                $meta['payment_terms'] = $value;
                $changed = true;
            }
        }

        if (array_key_exists('tax_percent', $payload)) {
            $value = $this->normalizeTaxPercent($payload['tax_percent']);
            unset($payload['tax_percent']);

            $existing = $meta['tax_percent'] ?? null;

            if ($value === null) {
                if (array_key_exists('tax_percent', $meta)) {
                    unset($meta['tax_percent']);
                    $changed = true;
                }
            } elseif ($existing !== $value) {
                $meta['tax_percent'] = $value;
                $changed = true;
            }
        }

        if ($meta === []) {
            if ($currentMeta !== null && $currentMeta !== []) {
                $changed = true;
            }

            return [null, $changed];
        }

        return [$meta, $changed];
    }

    private function normalizeMetaString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, 120, '');
    }

    private function normalizeTaxPercent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (float) $value;

        if ($normalized < 0 || $normalized > 100) {
            return null;
        }

        return round($normalized, 3);
    }

    /**
     * @param list<string> $dirty
     * @return list<string>
     */
    private function structuralDirtyFields(array $dirty): array
    {
        if ($dirty === []) {
            return [];
        }

        return array_values(array_intersect(self::STRUCTURAL_FIELDS, $dirty));
    }
}

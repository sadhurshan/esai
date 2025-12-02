<?php

namespace App\Http\Controllers\Api;

use App\Actions\PurchaseOrder\ConvertAwardsToPurchaseOrdersAction;
use App\Actions\PurchaseOrder\HandleSupplierAcknowledgementAction;
use App\Actions\PurchaseOrder\SendPurchaseOrderAction;
use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Http\Requests\PurchaseOrder\CreatePurchaseOrdersFromAwardsRequest;
use App\Http\Requests\PurchaseOrder\SendPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\SupplierAcknowledgeRequest;
use App\Http\Resources\PurchaseOrderDeliveryResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseOrderEventResource;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends ApiController
{
    public function __construct(
        private readonly ConvertAwardsToPurchaseOrdersAction $convertAwardsToPurchaseOrdersAction,
        private readonly SendPurchaseOrderAction $sendPurchaseOrder,
        private readonly HandleSupplierAcknowledgementAction $supplierAcknowledgement,
        private readonly AuditLogger $auditLogger,
    ) {}

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

        $isSupplierListing = $request->boolean('supplier') === true;

        $query = PurchaseOrder::query()->with(['quote.supplier', 'rfq']);

        if ($isSupplierListing) {
            $query->whereHas('quote.supplier', function ($supplierQuery) use ($companyId): void {
                $supplierQuery->where('company_id', $companyId);
            });
        } else {
            $query->where('company_id', $companyId);
        }

        $statusParam = $request->query('status');
        $statusFilter = $this->normalizeStatusFilter($statusParam);

        if (is_array($statusFilter) && $statusFilter !== []) {
            $query->whereIn('status', $statusFilter);
        } elseif (is_string($statusFilter) && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 25, 100))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, PurchaseOrderResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    public function show(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = $companyId === $purchaseOrder->company_id;
        $isSupplier = $supplierCompanyId !== null && $supplierCompanyId === $companyId;

        if (! $isBuyer && ! $isSupplier) {
            return $this->fail('Forbidden', 403);
        }

        $purchaseOrder->load([
            'lines.taxes.taxCode',
            'lines.invoiceLines',
            'lines.rfqItem',
            'rfq',
            'quote.supplier',
            'changeOrders.proposedByUser',
            'pdfDocument',
            'deliveries.creator',
        ]);

        if ($this->authorizeDenied($user, 'view', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request));
    }

    public function send(SendPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || $companyId !== $purchaseOrder->company_id) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'send', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $delivery = $this->sendPurchaseOrder->execute($user, $purchaseOrder, $request->payload());

        $purchaseOrder->refresh()->load([
            'lines.taxes.taxCode',
            'lines.invoiceLines',
            'lines.rfqItem',
            'rfq',
            'quote.supplier',
            'pdfDocument',
            'deliveries.creator',
        ]);

        $this->auditLogger->custom($purchaseOrder, 'purchase_order_sent', [
            'delivery_id' => $delivery->getKey(),
            'channel' => $delivery->channel,
            'recipients_to' => $delivery->recipients_to,
            'recipients_cc' => $delivery->recipients_cc,
        ]);

        return $this->ok([
            'purchase_order' => (new PurchaseOrderResource($purchaseOrder))->toArray($request),
            'delivery' => (new PurchaseOrderDeliveryResource($delivery))->toArray($request),
        ], 'Purchase order issued.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null || $companyId !== $purchaseOrder->company_id) {
            return $this->fail('Forbidden', 403);
        }

        if (! in_array($purchaseOrder->status, ['draft', 'sent'], true)) {
            return $this->fail('Only draft or sent purchase orders can be cancelled.', 422);
        }

        if ($this->authorizeDenied($user, 'cancel', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $before = $purchaseOrder->only(['status', 'cancelled_at']);
        $purchaseOrder->status = 'cancelled';
        $purchaseOrder->cancelled_at = now();
        $purchaseOrder->save();

        $this->auditLogger->updated($purchaseOrder, $before, [
            'status' => $purchaseOrder->status,
            'cancelled_at' => $purchaseOrder->cancelled_at,
        ]);

        Log::info('purchase_order.cancelled', [
            'purchase_order_id' => $purchaseOrder->id,
            'cancelled_by_user_id' => $user->id,
        ]);

        $purchaseOrder->load([
            'lines.taxes.taxCode',
            'lines.invoiceLines',
            'lines.rfqItem',
            'rfq',
            'quote.supplier',
            'pdfDocument',
        ]);

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request), 'Purchase order cancelled.');
    }

    public function acknowledge(SupplierAcknowledgeRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        if ($this->authorizeDenied($user, 'acknowledge', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $decision = $request->payload();

        $updated = $this->supplierAcknowledgement->execute(
            $user,
            $purchaseOrder,
            $decision['decision'],
            $decision['reason'] ?? null,
        );

        $updated->load([
            'lines.taxes.taxCode',
            'lines.invoiceLines',
            'lines.rfqItem',
            'rfq',
            'quote.supplier',
            'pdfDocument',
        ]);

        $this->auditLogger->custom($updated, 'purchase_order_supplier_decision', [
            'decision' => $decision['decision'],
            'reason' => $decision['reason'] ?? null,
            'actor_id' => $user->id,
        ]);

        $message = $decision['decision'] === 'acknowledged'
            ? 'Purchase order acknowledged.'
            : 'Purchase order declined by supplier.';

        return $this->ok((new PurchaseOrderResource($updated))->toArray($request), $message);
    }

    public function events(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = (int) $purchaseOrder->company_id === (int) $companyId;
        $isSupplier = $supplierCompanyId !== null && (int) $supplierCompanyId === (int) $companyId;

        if (! $isBuyer && ! $isSupplier) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'viewEvents', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $events = $purchaseOrder->events()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return $this->ok([
            'items' => PurchaseOrderEventResource::collection($events)->resolve(),
        ]);
    }

    public function export(PurchaseOrder $purchaseOrder, Request $request, DocumentStorer $documentStorer): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = $companyId === $purchaseOrder->company_id;
        $isSupplier = $supplierCompanyId !== null && $supplierCompanyId === $companyId;

        if (! $isBuyer && ! $isSupplier) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'export', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $document = $this->ensurePdfDocument($purchaseOrder, $user, $documentStorer);

        $downloadUrl = URL::signedRoute('purchase-orders.pdf.download', [
            'purchaseOrder' => $purchaseOrder->getKey(),
            'document' => $document->getKey(),
        ], now()->addMinutes(30));

        $documentPayload = [
            'id' => $document->getKey(),
            'filename' => $document->filename,
            'version' => $document->version_number,
            'download_url' => $downloadUrl,
            'created_at' => optional($document->created_at)?->toIso8601String(),
        ];

        return $this->ok([
            'document' => $documentPayload,
            'download_url' => $downloadUrl,
        ], 'Purchase order PDF ready.');
    }

    public function downloadPdf(Request $request, PurchaseOrder $purchaseOrder, Document $document): StreamedResponse|JsonResponse
    {
        if (
            $document->documentable_type !== PurchaseOrder::class
            || (int) $document->documentable_id !== (int) $purchaseOrder->getKey()
        ) {
            return $this->fail('Not found', 404);
        }

        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = $companyId === $purchaseOrder->company_id;
        $isSupplier = $supplierCompanyId !== null && $supplierCompanyId === $companyId;

        if (! $isBuyer && ! $isSupplier) {
            return $this->fail('Forbidden', 403);
        }

        if ($this->authorizeDenied($user, 'download', $purchaseOrder)) {
            return $this->fail('Forbidden', 403);
        }

        $disk = config('documents.disk', config('filesystems.default', 'local'));

        return Storage::disk($disk)->download($document->path, $document->filename);
    }

    public function createFromAwards(CreatePurchaseOrdersFromAwardsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $awardIds = $request->validated('award_ids');

        $awards = RfqItemAward::query()
            ->whereIn('id', $awardIds)
            ->get();

        if ($awards->isEmpty()) {
            throw ValidationException::withMessages([
                'award_ids' => ['No awards were found for the provided identifiers.'],
            ]);
        }

        $companyMismatch = $awards->pluck('company_id')->unique()->count() !== 1
            || (int) $awards->first()->company_id !== (int) $companyId;

        if ($companyMismatch) {
            return $this->fail('Forbidden', 403);
        }

        CompanyContext::bypass(fn () => $awards->load(['rfq', 'supplier.company', 'quote.supplier', 'quoteItem.rfqItem']));

        $rfq = $awards->first()->rfq;

        if (! $rfq instanceof RFQ) {
            $rfq = RFQ::query()->find($awards->first()->rfq_id);
        }

        if (! $rfq instanceof RFQ) {
            throw ValidationException::withMessages([
                'award_ids' => ['Awards must be associated with a valid RFQ.'],
            ]);
        }

        Gate::forUser($user)->authorize('awardLines', $rfq);

        $purchaseOrders = $this->convertAwardsToPurchaseOrdersAction->execute($rfq, $awards);

        $response = $this->ok([
            'purchase_orders' => PurchaseOrderResource::collection($purchaseOrders)->resolve(),
        ], 'Purchase orders drafted from awards.');

        $response->setStatusCode(201);

        return $response;
    }

    private function normalizeStatusFilter(mixed $statusParam): array|string|null
    {
        if (is_array($statusParam)) {
            return array_values(array_filter(array_map('strval', $statusParam), fn (string $value): bool => $value !== ''));
        }

        if (is_string($statusParam)) {
            $parts = array_filter(array_map('trim', explode(',', $statusParam)));

            if (count($parts) > 1) {
                return array_values($parts);
            }

            return $parts[0] ?? null;
        }

        return null;
    }

    private function ensurePdfDocument(
        PurchaseOrder $purchaseOrder,
        User $user,
        DocumentStorer $documentStorer
    ): Document {
        $purchaseOrder->load([
            'lines.taxes.taxCode',
            'lines.rfqItem',
            'quote.supplier',
            'company',
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view('pdf.purchase-order', [
            'purchaseOrder' => $purchaseOrder,
        ])->render();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $tempDisk = Storage::disk('local');
        $tempPath = sprintf('tmp/po-export-%s.pdf', Str::uuid()->toString());
        $tempDisk->put($tempPath, $dompdf->output());

        $uploadedFile = new UploadedFile(
            $tempDisk->path($tempPath),
            sprintf('purchase-order-%s.pdf', $purchaseOrder->po_number ?? $purchaseOrder->getKey()),
            'application/pdf',
            null,
            true
        );

        $document = $documentStorer->store(
            $user,
            $uploadedFile,
            DocumentCategory::Commercial->value,
            $purchaseOrder->company_id,
            PurchaseOrder::class,
            $purchaseOrder->getKey(),
            [
                'kind' => DocumentKind::PurchaseOrder->value,
                'meta' => [
                    'generated' => true,
                    'source' => 'purchase_order_export',
                ],
                'visibility' => 'company',
            ]
        );

        $purchaseOrder->pdf_document_id = $document->getKey();
        $purchaseOrder->save();
        $purchaseOrder->setRelation('pdfDocument', $document);

        $tempDisk->delete($tempPath);

        return $document;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Http\Requests\RFQQuoteStoreRequest;
use App\Http\Resources\RFQQuoteResource;
use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use App\Support\Security\VirusScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RFQQuoteController extends ApiController
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly VirusScanner $virusScanner,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(string $rfqId, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        try {
            $rfq = CompanyContext::bypass(static fn () => RFQ::query()->with('invitations')->find($rfqId));

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $accessScope = $this->determineAccessScope($user, $rfq, $companyId);

            if ($accessScope === null) {
                return $this->fail('Forbidden', 403);
            }

            $perPage = $this->perPage($request);
            $cursorName = 'cursor';
            $cursor = $request->query($cursorName);

            if ($accessScope === 'buyer') {
                $paginator = RFQQuote::query()
                    ->with('supplier')
                    ->where('rfq_id', $rfq->id)
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->cursorPaginate($perPage, ['*'], $cursorName, $cursor);
            } else {
                $supplierIds = $this->supplierIdsForCompany($companyId);

                if ($supplierIds === []) {
                    return $this->fail('Supplier context missing.', 403, [
                        'code' => 'supplier_context_missing',
                    ]);
                }

                $paginator = CompanyContext::bypass(fn () => RFQQuote::query()
                    ->with('supplier')
                    ->where('rfq_id', $rfq->id)
                    ->whereIn('supplier_id', $supplierIds)
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->cursorPaginate($perPage, ['*'], $cursorName, $cursor));
            }

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RFQQuoteResource::class);

            return $this->ok([
                'items' => $items,
            ], null, $meta);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function store(string $rfqId, RFQQuoteStoreRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if (! $this->isSupplierUser($user)) {
            return $this->fail('Only supplier roles may submit RFQ quotes.', 403);
        }

        try {
            $rfq = CompanyContext::bypass(static fn () => RFQ::query()->with('invitations')->find($rfqId));

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $payload = $request->validated();
            $supplier = CompanyContext::bypass(static fn () => Supplier::query()->with('company')->find((int) $payload['supplier_id']));

            if (! $supplier || (int) $supplier->company_id !== (int) $companyId) {
                return $this->fail('Supplier not found for your company.', 403);
            }

            if (! $this->supplierHasAccessToRfq($rfq, $supplier->id)) {
                return $this->fail('Supplier is not invited to this RFQ.', 403, [
                    'code' => 'rfq_invitation_required',
                ]);
            }

            /** @var UploadedFile|null $attachment */
            $attachment = $request->file('attachment');

            $quote = DB::transaction(function () use ($rfq, $supplier, $payload, $attachment, $user) {
                $quote = RFQQuote::create([
                    'company_id' => $rfq->company_id,
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplier->id,
                    'unit_price_usd' => $payload['unit_price_usd'],
                    'lead_time_days' => (int) $payload['lead_time_days'],
                    'note' => $payload['note'] ?? null,
                    'via' => $payload['via'],
                    'submitted_at' => now(),
                ]);

                if ($attachment instanceof UploadedFile) {
                    $this->attachDocument($quote, $rfq, $supplier, $attachment, $user);
                }

                $this->auditLogger->created($quote);

                return $quote;
            });

            $quote->load('supplier');

            return $this->ok((new RFQQuoteResource($quote))->toArray($request), 'RFQ quote created')->setStatusCode(201);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    private function attachDocument(RFQQuote $quote, RFQ $rfq, Supplier $supplier, UploadedFile $file, User $actor): void
    {
        $this->virusScanner->assertClean($file, [
            'rfq_id' => $rfq->id,
            'rfq_company_id' => $rfq->company_id,
            'quote_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'user_id' => $actor->id,
        ]);

        $document = $this->documentStorer->store(
            $actor,
            $file,
            DocumentCategory::Commercial->value,
            $rfq->company_id,
            $quote->getMorphClass(),
            (int) $quote->getKey(),
            [
                'kind' => DocumentKind::Quote->value,
                'visibility' => 'company',
                'meta' => [
                    'context' => 'rfq_quote_attachment',
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplier->id,
                    'quote_id' => $quote->id,
                ],
            ]
        );

        $quote->forceFill(['attachment_path' => $document->path])->save();
    }

    private function determineAccessScope(User $user, RFQ $rfq, int $companyId): ?string
    {
        if ((int) $rfq->company_id === (int) $companyId) {
            return $this->authorizeDenied($user, 'view', $rfq) ? null : 'buyer';
        }

        if ($this->isSupplierUser($user) && $this->supplierCanAccessCompanyRfq($companyId, $rfq)) {
            return 'supplier';
        }

        if ($user->isPlatformAdmin()) {
            return 'buyer';
        }

        return null;
    }

    private function supplierHasAccessToRfq(RFQ $rfq, int $supplierId): bool
    {
        if ((bool) $rfq->open_bidding) {
            return true;
        }

        return CompanyContext::bypass(static function () use ($rfq, $supplierId): bool {
            return $rfq->invitations()
                ->where('supplier_id', $supplierId)
                ->exists();
        });
    }

    private function supplierCanAccessCompanyRfq(int $supplierCompanyId, RFQ $rfq): bool
    {
        $supplierIds = $this->supplierIdsForCompany($supplierCompanyId);

        if ($supplierIds === []) {
            return false;
        }

        if ((bool) $rfq->open_bidding) {
            return true;
        }

        $invitedIds = CompanyContext::bypass(static function () use ($rfq): array {
            return $rfq->invitations()
                ->pluck('supplier_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        });

        return array_intersect($supplierIds, $invitedIds) !== [];
    }

    /**
     * @return list<int>
     */
    private function supplierIdsForCompany(int $companyId): array
    {
        return CompanyContext::bypass(static function () use ($companyId): array {
            return Supplier::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        });
    }

    private function isSupplierUser(User $user): bool
    {
        return Str::startsWith((string) ($user->role ?? ''), 'supplier_');
    }
}

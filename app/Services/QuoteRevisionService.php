<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Exceptions\QuoteActionException;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\QuoteRevision;
use App\Models\RFQ;
use App\Models\User;
use App\Services\DigitalTwin\DigitalTwinLinkService;
use App\Support\ActivePersonaContext;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuoteRevisionService
{
    /**
     * @var list<string>
     */
    private array $buyerRoles = ['owner', 'buyer_admin', 'buyer_requester', 'buyer_viewer', 'finance'];

    /**
     * @var list<string>
     */
    private array $supplierRoles = ['supplier_admin', 'supplier_estimator', 'owner'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
        private readonly NotificationService $notifications,
        private readonly PricingObservationService $pricingObservationService,
        private readonly DigitalTwinLinkService $digitalTwinLinkService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submitRevision(Quote $quote, array $payload, ?UploadedFile $attachment, User $supplier): QuoteRevision
    {
        CompanyContext::bypass(static function () use ($quote): void {
            $quote->load(['rfq', 'supplier.company', 'company', 'items']);
        });

        $this->assertSupplierOwnsQuote($quote, $supplier);
        $this->assertQuoteActive($quote);
        $this->assertDeadlineNotPassed($quote);

        $revision = CompanyContext::bypass(function () use ($quote, $payload, $attachment, $supplier): QuoteRevision {
            return DB::transaction(function () use ($quote, $payload, $attachment, $supplier): QuoteRevision {
                $nextRevision = ($quote->revision_no ?? 1) + 1;

                $revision = QuoteRevision::create([
                    'company_id' => $quote->company_id,
                    'quote_id' => $quote->id,
                    'revision_no' => $nextRevision,
                    'data_json' => array_merge($payload, [
                        'submitted_by' => $supplier->id,
                    ]),
                ]);

                if ($attachment instanceof UploadedFile) {
                    $document = $this->documentStorer->store(
                        $supplier,
                        $attachment,
                        DocumentCategory::Commercial->value,
                        $quote->company_id,
                        $revision->getMorphClass(),
                        $revision->id,
                        [
                            'kind' => DocumentKind::Quote->value,
                            'visibility' => 'company',
                            'meta' => [
                                'context' => 'quote_revision_attachment',
                                'quote_id' => $quote->id,
                                'revision_no' => $nextRevision,
                            ],
                        ]
                    );

                    $revision->document_id = $document->id;
                    $revision->setRelation('document', $document);
                    $revision->save();
                }

                $this->applyQuoteUpdates($quote, $payload, $nextRevision);
                $this->updateQuoteItems($quote, $payload['items'] ?? []);
                $this->pricingObservationService->recordQuoteRevision($quote, $revision);

                $this->auditLogger->created($revision, [
                    'quote_id' => $quote->id,
                    'revision_no' => $revision->revision_no,
                ]);

                return $revision->fresh(['document']);
            });
        });

        $refreshedQuote = CompanyContext::bypass(static fn () => $quote->fresh(['supplier', 'company']));

        $rfq = $quote->rfq;

        if ($rfq instanceof RFQ) {
            $this->digitalTwinLinkService->linkQuoteSubmission($rfq, $quote, $supplier);
        }

        $supplierName = $refreshedQuote?->supplier?->name ?? 'A supplier';

        $this->notifyBuyers(
            $refreshedQuote ?? $quote,
            $supplier,
            'quote.revision.submitted',
            'Quote revision submitted',
            sprintf('%s submitted revision %s for quote %s.', $supplierName, $revision->revision_no, $quote->id),
            [
                'quote_id' => $quote->id,
                'revision_id' => $revision->id,
                'revision_no' => $revision->revision_no,
                'submitted_by' => $supplier->id,
            ]
        );

        return $revision;
    }

    public function withdrawQuote(Quote $quote, User $supplier, string $reason): Quote
    {
        CompanyContext::bypass(static function () use ($quote): void {
            $quote->load(['rfq', 'supplier.company', 'company']);
        });

        $this->assertSupplierOwnsQuote($quote, $supplier);
        $this->assertQuoteActive($quote);
        $this->assertDeadlineNotPassed($quote);

        $withdrawn = CompanyContext::bypass(function () use ($quote, $reason): Quote {
            return DB::transaction(function () use ($quote, $reason): Quote {
                $before = $quote->getOriginal();

                $quote->withdrawn_at = now();
                $quote->withdraw_reason = $reason;
                $quote->status = 'withdrawn';
                $quote->save();

                $this->auditLogger->updated($quote, $before, $quote->only(['withdrawn_at', 'withdraw_reason', 'status']));

                return $quote->fresh(['supplier', 'items', 'revisions.document']);
            });
        });

        $supplierName = $withdrawn->supplier?->name ?? 'A supplier';

        $this->notifyBuyers(
            $withdrawn,
            $supplier,
            'quote.withdrawn',
            'Quote withdrawn',
            sprintf('%s withdrew quote %s: %s', $supplierName, $withdrawn->id, $reason),
            [
                'quote_id' => $withdrawn->id,
                'withdrawn_at' => optional($withdrawn->withdrawn_at)?->toIso8601String(),
                'reason' => $reason,
                'submitted_by' => $supplier->id,
            ]
        );

        return $withdrawn;
    }

    private function assertSupplierOwnsQuote(Quote $quote, User $supplier): void
    {
        $supplierCompanyId = $quote->supplier?->company_id;
        $userCompanyId = $supplier->company_id;
        $matchesUserCompany = $supplierCompanyId !== null
            && $userCompanyId !== null
            && (int) $supplierCompanyId === (int) $userCompanyId;

        if (! $matchesUserCompany && ! $this->personaOwnsQuoteSupplier($quote, $supplierCompanyId)) {
            throw new QuoteActionException('You cannot modify this quote.', 403);
        }

        if (! in_array($supplier->role, [...$this->supplierRoles, 'platform_super', 'platform_support'], true)) {
            throw new QuoteActionException('You are not permitted to modify this quote.', 403);
        }
    }

    private function personaOwnsQuoteSupplier(Quote $quote, ?int $supplierCompanyId): bool
    {
        if (! ActivePersonaContext::isSupplier()) {
            return false;
        }

        $personaSupplierId = ActivePersonaContext::supplierId();

        if ($personaSupplierId !== null && (int) $personaSupplierId === (int) $quote->supplier_id) {
            return true;
        }

        $personaSupplierCompanyId = ActivePersonaContext::get()?->supplierCompanyId();

        if ($personaSupplierCompanyId === null || $supplierCompanyId === null) {
            return false;
        }

        return (int) $personaSupplierCompanyId === (int) $supplierCompanyId;
    }

    private function assertQuoteActive(Quote $quote): void
    {
        if ($quote->withdrawn_at !== null || $quote->status === 'withdrawn') {
            throw new QuoteActionException('Quote has been withdrawn.', 400);
        }
    }

    private function assertDeadlineNotPassed(Quote $quote): void
    {
        $deadline = $quote->rfq?->due_at ?? $quote->rfq?->deadline_at;

        if ($deadline !== null && now()->greaterThan($deadline)) {
            throw new QuoteActionException('Deadline passed', 400);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyQuoteUpdates(Quote $quote, array $payload, int $nextRevision): void
    {
        $fields = ['unit_price', 'min_order_qty', 'lead_time_days', 'note', 'currency'];

        $updates = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        $updates['revision_no'] = $nextRevision;

        $before = Arr::only($quote->getAttributes(), array_keys($updates));

        $quote->fill($updates);
        $quote->save();

        $this->auditLogger->updated($quote, $before, Arr::only($quote->getAttributes(), array_keys($updates)));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function updateQuoteItems(Quote $quote, array $items): void
    {
        if ($items === []) {
            return;
        }

        $itemsById = collect($items)->keyBy('quote_item_id');

        $quoteItems = QuoteItem::query()
            ->where('quote_id', $quote->id)
            ->whereIn('id', $itemsById->keys())
            ->get();

        foreach ($quoteItems as $item) {
            $payload = $itemsById->get($item->id, []);
            $before = Arr::only($item->getAttributes(), ['unit_price', 'lead_time_days', 'note']);

            foreach (['unit_price', 'lead_time_days', 'note'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $item->{$field} = $payload[$field];
                }
            }

            if ($item->isDirty()) {
                $item->save();
                $this->auditLogger->updated($item, $before, Arr::only($item->getAttributes(), ['unit_price', 'lead_time_days', 'note']));
            }
        }
    }

    private function notifyBuyers(Quote $quote, User $supplier, string $event, string $title, string $body, array $meta): void
    {
        $recipients = $this->buyerRecipients($quote)
            ->reject(static fn (User $user) => $user->id === $supplier->id);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            $event,
            $title,
            $body,
            Quote::class,
            $quote->id,
            $meta
        );
    }

    private function buyerRecipients(Quote $quote): Collection
    {
        return User::query()
            ->where('company_id', $quote->company_id)
            ->whereIn('role', [...$this->buyerRoles, 'platform_super', 'platform_support'])
            ->get();
    }
}

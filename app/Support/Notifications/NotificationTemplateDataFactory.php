<?php

namespace App\Support\Notifications;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Services\FormattingService;
use App\Support\Money\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class NotificationTemplateDataFactory
{
    private const CTA_URL_KEYS = ['cta_url', 'url', 'href', 'link', 'path'];

    private const CTA_LABEL_KEYS = ['cta_label', 'label', 'button_label'];

    public function __construct(private readonly FormattingService $formattingService)
    {
    }

    /**
     * @return array{view:string,data:array<string,mixed>}
     */
    public function build(Notification $notification): array
    {
        $notification->loadMissing(['company', 'user']);

        $normalized = $this->normalizeEvent($notification->event_type);

        if ($normalized === 'invoice_status_changed' && $this->isCreditNoteStatusChange($notification)) {
            return $this->creditNotePosted($notification);
        }

        return match ($normalized) {
            'rfq_created', 'rfq_published' => $this->rfqPublished($notification),
            'quote_submitted', 'quote.revision.submitted' => $this->quoteSubmitted($notification),
            'quote.withdrawn' => $this->quoteWithdrawn($notification),
            'po_issued' => $this->poSent($notification),
            'po_acknowledged' => $this->poAcknowledged($notification),
            'invoice_created', 'invoice_posted' => $this->invoicePosted($notification),
            'grn_posted' => $this->grnPosted($notification),
            'credit_note_posted' => $this->creditNotePosted($notification),
            default => $this->generic($notification),
        };
    }

    private function rfqPublished(Notification $notification): array
    {
        $rfq = $this->resolveEntity($notification, RFQ::class, ['company', 'creator']);
        $company = $this->resolveCompany($notification, $rfq?->company);

        $deadline = $rfq?->deadline_at ?? $rfq?->due_at ?? $rfq?->close_at ?? Arr::get($notification->meta, 'deadline_at');

        return [
            'view' => 'emails.notifications.rfq-published',
            'data' => $this->baseData($notification, [
                'companyName' => $rfq?->company?->name
                    ?? $company?->name
                    ?? Arr::get($notification->meta, 'company_name'),
                'rfqNumber' => $rfq?->number ?? Arr::get($notification->meta, 'rfq_number'),
                'rfqTitle' => $rfq?->title ?? Arr::get($notification->meta, 'rfq_title'),
                'ownerName' => $rfq?->creator?->name ?? Arr::get($notification->meta, 'owner_name'),
                'submissionDeadline' => $this->formatDate($deadline, $company),
            ], 'Open RFQ'),
        ];
    }

    private function quoteSubmitted(Notification $notification): array
    {
        $quote = $this->resolveEntity($notification, Quote::class, ['company', 'supplier', 'rfq']);
        $company = $this->resolveCompany($notification, $quote?->company);
        $submittedAt = $quote?->submitted_at ?? Arr::get($notification->meta, 'submitted_at') ?? $notification->created_at;

        return [
            'view' => 'emails.notifications.quote-submitted',
            'data' => $this->baseData($notification, [
                'supplierName' => $quote?->supplier?->name ?? Arr::get($notification->meta, 'supplier_name'),
                'quoteNumber' => $this->stringify($quote?->id ?? Arr::get($notification->meta, 'quote_number')), 
                'rfqNumber' => $quote?->rfq?->number ?? Arr::get($notification->meta, 'rfq_number'),
                'submittedAt' => $this->formatDate($submittedAt, $company),
                'quoteTotal' => $this->formatMoneyValue(
                    $quote?->total_minor,
                    $quote?->currency,
                    $quote?->total ?? Arr::get($notification->meta, 'total'),
                    $company
                ),
            ], 'Open quote'),
        ];
    }

    private function quoteWithdrawn(Notification $notification): array
    {
        $quote = $this->resolveEntity($notification, Quote::class, ['company', 'supplier', 'rfq']);
        $company = $this->resolveCompany($notification, $quote?->company);
        $withdrawnAt = $quote?->withdrawn_at ?? Arr::get($notification->meta, 'withdrawn_at') ?? $notification->updated_at;

        return [
            'view' => 'emails.notifications.quote-withdrawn',
            'data' => $this->baseData($notification, [
                'supplierName' => $quote?->supplier?->name ?? Arr::get($notification->meta, 'supplier_name'),
                'quoteNumber' => $this->stringify($quote?->id ?? Arr::get($notification->meta, 'quote_number')),
                'rfqNumber' => $quote?->rfq?->number ?? Arr::get($notification->meta, 'rfq_number'),
                'withdrawnAt' => $this->formatDate($withdrawnAt, $company),
                'withdrawReason' => $quote?->withdraw_reason ?? Arr::get($notification->meta, 'reason'),
            ], 'Review RFQ'),
        ];
    }

    private function poSent(Notification $notification): array
    {
        $po = $this->resolveEntity($notification, PurchaseOrder::class, ['company', 'supplier']);
        $company = $this->resolveCompany($notification, $po?->company);
        $sentAt = $po?->sent_at ?? Arr::get($notification->meta, 'sent_at') ?? $notification->created_at;

        return [
            'view' => 'emails.notifications.po-sent',
            'data' => $this->baseData($notification, [
                'poNumber' => $po?->po_number ?? Arr::get($notification->meta, 'po_number'),
                'supplierName' => $po?->supplier?->name ?? Arr::get($notification->meta, 'supplier_name'),
                'sentAt' => $this->formatDate($sentAt, $company),
                'poTotal' => $this->formatMoneyValue(
                    $po?->total_minor,
                    $po?->currency,
                    $po?->total ?? Arr::get($notification->meta, 'total'),
                    $company
                ),
            ], 'Open PO'),
        ];
    }

    private function poAcknowledged(Notification $notification): array
    {
        $po = $this->resolveEntity($notification, PurchaseOrder::class, ['company', 'supplier']);
        $company = $this->resolveCompany($notification, $po?->company);
        $ackAt = $po?->acknowledged_at ?? Arr::get($notification->meta, 'acknowledged_at') ?? $notification->updated_at;

        return [
            'view' => 'emails.notifications.po-acknowledged',
            'data' => $this->baseData($notification, [
                'poNumber' => $po?->po_number ?? Arr::get($notification->meta, 'po_number'),
                'supplierName' => $po?->supplier?->name ?? Arr::get($notification->meta, 'supplier_name'),
                'acknowledgedAt' => $this->formatDate($ackAt, $company),
                'ackStatus' => $po?->ack_status ?? Arr::get($notification->meta, 'ack_status') ?? 'acknowledged',
            ], 'View acknowledgement'),
        ];
    }

    private function invoicePosted(Notification $notification): array
    {
        $invoice = $this->resolveEntity($notification, Invoice::class, ['company', 'purchaseOrder']);
        $company = $this->resolveCompany($notification, $invoice?->company);
        $dueDate = Arr::get($notification->meta, 'due_at') ?? $invoice?->invoice_date;

        return [
            'view' => 'emails.notifications.invoice-posted',
            'data' => $this->baseData($notification, [
                'invoiceNumber' => $invoice?->invoice_number ?? Arr::get($notification->meta, 'invoice_number'),
                'poNumber' => $invoice?->purchaseOrder?->po_number ?? Arr::get($notification->meta, 'po_number'),
                'invoiceTotal' => $this->formatMoneyValue(
                    null,
                    $invoice?->currency ?? Arr::get($notification->meta, 'currency'),
                    $invoice?->total ?? Arr::get($notification->meta, 'total'),
                    $company
                ),
                'dueDate' => $this->formatDate($dueDate, $company),
            ], 'Open invoice'),
        ];
    }

    private function grnPosted(Notification $notification): array
    {
        $grn = $this->resolveEntity($notification, GoodsReceiptNote::class, ['company', 'purchaseOrder', 'lines']);
        $company = $this->resolveCompany($notification, $grn?->company);
        $receivedAt = $grn?->inspected_at ?? Arr::get($notification->meta, 'received_at') ?? $grn?->created_at;
        $receivedQty = $grn?->lines?->sum('received_qty');

        return [
            'view' => 'emails.notifications.grn-posted',
            'data' => $this->baseData($notification, [
                'grnNumber' => $grn?->number ?? Arr::get($notification->meta, 'grn_number'),
                'poNumber' => $grn?->purchaseOrder?->po_number ?? Arr::get($notification->meta, 'po_number'),
                'receivedDate' => $this->formatDate($receivedAt, $company),
                'receivedQuantity' => $receivedQty !== null
                    ? number_format((float) $receivedQty)
                    : $this->stringify(Arr::get($notification->meta, 'received_quantity')),
            ], 'Review receipt'),
        ];
    }

    private function creditNotePosted(Notification $notification): array
    {
        $creditNote = $this->resolveEntity($notification, CreditNote::class, ['company', 'invoice']);
        $company = $this->resolveCompany($notification, $creditNote?->company);

        return [
            'view' => 'emails.notifications.credit-note-posted',
            'data' => $this->baseData($notification, [
                'creditNoteNumber' => $creditNote?->credit_number ?? Arr::get($notification->meta, 'credit_number'),
                'invoiceNumber' => $creditNote?->invoice?->invoice_number ?? Arr::get($notification->meta, 'invoice_number'),
                'creditAmount' => $this->formatMoneyValue(
                    $creditNote?->amount_minor,
                    $creditNote?->currency,
                    $creditNote?->amount ?? Arr::get($notification->meta, 'amount'),
                    $company
                ),
                'creditReason' => $creditNote?->reason ?? Arr::get($notification->meta, 'reason'),
            ], 'Review credit note'),
        ];
    }

    private function generic(Notification $notification): array
    {
        return [
            'view' => 'emails.notifications.generic',
            'data' => $this->baseData($notification, ['meta' => $notification->meta ?? []], 'View details'),
        ];
    }

    private function baseData(Notification $notification, array $overrides = [], ?string $ctaFallback = null): array
    {
        $data = [
            'notification' => $notification,
        ];

        if ($ctaUrl = $this->resolveCtaUrl($notification)) {
            $data['ctaUrl'] = $ctaUrl;
        }

        if ($ctaLabel = $this->resolveCtaLabel($notification, $ctaFallback)) {
            $data['ctaLabel'] = $ctaLabel;
        }

        if ($overrides !== []) {
            $data = array_merge($data, $overrides);
        }

        return $data;
    }

    private function resolveCtaUrl(Notification $notification): ?string
    {
        foreach (self::CTA_URL_KEYS as $key) {
            $value = Arr::get($notification->meta, $key);
            if (is_string($value) && $value !== '') {
                return $this->normalizeUrl($value);
            }
        }

        return null;
    }

    private function resolveCtaLabel(Notification $notification, ?string $fallback = null): ?string
    {
        foreach (self::CTA_LABEL_KEYS as $key) {
            $value = Arr::get($notification->meta, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function normalizeUrl(string $value): string
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'mailto:')) {
            return $value;
        }

        $base = config('app.url');

        if (! is_string($base) || $base === '') {
            return $value;
        }

        return rtrim($base, '/').'/'.ltrim($value, '/');
    }

    private function resolveCompany(Notification $notification, ?Company $fallback = null): ?Company
    {
        if ($notification->relationLoaded('company') && $notification->company instanceof Company) {
            return $notification->company;
        }

        if ($notification->company instanceof Company) {
            return $notification->company;
        }

        if ($fallback instanceof Company) {
            return $fallback;
        }

        if ($notification->company_id !== null) {
            /** @var Company|null $company */
            $company = Company::query()->find($notification->company_id);

            return $company;
        }

        return null;
    }

    /**
     * @template TModel of Model
     * @param class-string<TModel> $class
     * @param array<int, string> $relations
     * @return TModel|null
     */
    private function resolveEntity(Notification $notification, string $class, array $relations = []): ?Model
    {
        $id = $this->resolveEntityKey($notification, $class);

        if ($id === null) {
            return null;
        }

        $query = $class::query();

        if ($relations !== []) {
            $query->with($relations);
        }

        /** @var TModel|null $model */
        $model = $query->find($id);

        return $model;
    }

    private function resolveEntityKey(Notification $notification, string $class): ?int
    {
        $entityType = $notification->entity_type;
        $entityId = $notification->entity_id;

        if ($entityId !== null && ($entityType === null || is_a($entityType, $class, true))) {
            return (int) $entityId;
        }

        $metaKeys = match ($class) {
            RFQ::class => ['rfq_id'],
            Quote::class => ['quote_id'],
            PurchaseOrder::class => ['po_id', 'purchase_order_id'],
            Invoice::class => ['invoice_id'],
            GoodsReceiptNote::class => ['grn_id', 'goods_receipt_note_id'],
            CreditNote::class => ['credit_note_id'],
            default => [],
        };

        foreach ($metaKeys as $key) {
            $value = Arr::get($notification->meta, $key);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function formatMoneyValue(?int $amountMinor, ?string $currency, float|int|string|null $decimal, ?Company $company): ?string
    {
        if ($currency === null || ! $company instanceof Company) {
            return null;
        }

        $money = null;

        if ($amountMinor !== null) {
            $money = Money::fromMinor($amountMinor, $currency);
        } elseif ($decimal !== null && is_numeric($decimal)) {
            $minorUnit = $this->resolveMinorUnit($currency);
            $money = Money::fromDecimal((float) $decimal, $currency, $minorUnit);
        }

        if ($money === null) {
            return null;
        }

        return $this->formattingService->formatMoney($money, $company);
    }

    private function resolveMinorUnit(string $currency): int
    {
        $code = strtoupper($currency);
        $cacheKey = 'currency_minor_unit:'.$code;

        return Cache::rememberForever($cacheKey, function () use ($code): int {
            $record = Currency::query()->where('code', $code)->first();

            return $record?->minor_unit ?? 2;
        });
    }

    private function formatDate(CarbonInterface|string|null $value, ?Company $company): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $this->normalizeDate($value);

        if ($date === null) {
            return null;
        }

        if ($company instanceof Company) {
            return $this->formattingService->formatDate($date, $company);
        }

        return $date->toDayDateTimeString();
    }

    private function normalizeDate(CarbonInterface|string $value): ?Carbon
    {
        try {
            if ($value instanceof Carbon) {
                return $value;
            }

            if ($value instanceof CarbonInterface) {
                return Carbon::instance($value->toDateTime());
            }

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    private function normalizeEvent(?string $event): ?string
    {
        return $event !== null ? strtolower($event) : null;
    }

    private function isCreditNoteStatusChange(Notification $notification): bool
    {
        $event = Arr::get($notification->meta, 'credit_event');

        return is_string($event) && str_starts_with($event, 'credit_note.');
    }
}

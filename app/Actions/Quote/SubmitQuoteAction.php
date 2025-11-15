<?php

namespace App\Actions\Quote;

use App\Enums\MoneyRoundRule;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RfqItem;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Support\Documents\DocumentStorer;
use App\Support\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SubmitQuoteAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync
    ) {}

    /**
     * @param array{
     *     company_id: int,
     *     rfq_id: int,
     *     supplier_id: int,
     *     submitted_by: int|null,
     *     currency: string,
     *     unit_price?: string|float|null,
     *     min_order_qty?: int|null,
     *     lead_time_days: int,
     *     note?: string|null,
     *     status?: string|null,
     *     revision_no?: int|null,
     *     items: array<int, array{
     *         rfq_item_id: int,
     *         unit_price?: string|float|null,
     *         unit_price_minor?: int|null,
     *         currency?: string|null,
     *         lead_time_days: int,
     *         tax_code_ids?: array<int, int>,
     *         note?: string|null,
     *         status?: string|null
     *     }>
     * } $data
     */
    public function execute(array $data, ?UploadedFile $attachment = null): Quote
    {
        $companyId = (int) $data['company_id'];
        $rfqId = (int) $data['rfq_id'];
        $currency = strtoupper((string) $data['currency']);

        $itemsPayload = collect($data['items'] ?? []);

        if ($itemsPayload->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['At least one quote item is required.'],
            ]);
        }

        $rfqItems = RfqItem::query()
            ->where('rfq_id', $rfqId)
            ->whereIn('id', $itemsPayload->pluck('rfq_item_id')->all())
            ->get()
            ->keyBy('id');

        if ($rfqItems->count() !== $itemsPayload->count()) {
            throw ValidationException::withMessages([
                'items' => ['One or more RFQ items are invalid for this quote.'],
            ]);
        }

        [$lineInputs, $totalQuantity] = $this->buildLineInputs($itemsPayload, $rfqItems, $currency);

        $calculation = $this->totalsCalculator->calculate($companyId, $currency, $lineInputs);
        $minorUnit = (int) $calculation['minor_unit'];
        $roundRule = MoneyRoundRule::from($calculation['round_rule']);

        $subtotalMinor = (int) $calculation['totals']['subtotal_minor'];
        $taxMinor = (int) $calculation['totals']['tax_total_minor'];
        $totalMinor = (int) $calculation['totals']['grand_total_minor'];

        $averageUnitMoney = $totalQuantity > 0
            ? Money::fromMinor($totalMinor, $currency)->divide($totalQuantity, $roundRule)
            : Money::fromMinor($totalMinor, $currency);

        return DB::transaction(function () use (
            $data,
            $attachment,
            $currency,
            $minorUnit,
            $roundRule,
            $subtotalMinor,
            $taxMinor,
            $totalMinor,
            $averageUnitMoney,
            $itemsPayload,
            $companyId,
            $calculation
        ): Quote {
            $quote = Quote::create([
                'company_id' => (int) $data['company_id'],
                'rfq_id' => (int) $data['rfq_id'],
                'supplier_id' => (int) $data['supplier_id'],
                'submitted_by' => $data['submitted_by'] ?? null,
                'submitted_at' => (($data['status'] ?? 'submitted') === 'submitted') ? now() : null,
                'currency' => $currency,
                'unit_price' => $averageUnitMoney->toDecimal($minorUnit),
                'min_order_qty' => $data['min_order_qty'] ?? null,
                'lead_time_days' => (int) $data['lead_time_days'],
                'note' => $data['note'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'revision_no' => $data['revision_no'] ?? 1,
                'subtotal' => Money::fromMinor($subtotalMinor, $currency)->toDecimal($minorUnit),
                'tax_amount' => Money::fromMinor($taxMinor, $currency)->toDecimal($minorUnit),
                'total' => Money::fromMinor($totalMinor, $currency)->toDecimal($minorUnit),
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxMinor,
                'total_minor' => $totalMinor,
            ]);

            $lineResults = collect($calculation['lines'])->keyBy('key');

            $itemsPayload->each(function (array $item) use ($quote, $currency, $minorUnit, $lineResults, $companyId): void {
                $rfqItemId = (int) $item['rfq_item_id'];
                $result = $lineResults->get($rfqItemId);

                if ($result === null) {
                    throw ValidationException::withMessages([
                        'items' => ['Unable to calculate totals for one or more quote items.'],
                    ]);
                }

                $unitPrice = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);

                $quoteItem = QuoteItem::create([
                    'quote_id' => $quote->id,
                    'rfq_item_id' => $rfqItemId,
                    'unit_price' => $unitPrice,
                    'unit_price_minor' => $result['unit_price_minor'],
                    'currency' => $currency,
                    'lead_time_days' => (int) $item['lead_time_days'],
                    'note' => $item['note'] ?? null,
                    'status' => $item['status'] ?? 'pending',
                ]);

                $this->lineTaxSync->sync($quoteItem, $companyId, $result['taxes']);
            });

            if ($attachment instanceof UploadedFile) {
                $this->documentStorer->store(
                    auth()->user(),
                    $attachment,
                    'commercial',
                    $quote->company_id,
                    $quote->getMorphClass(),
                    $quote->id,
                    [
                        'kind' => 'quote',
                        'visibility' => 'company',
                        'meta' => ['context' => 'quote_attachment'],
                    ]
                );
            }

            $this->auditLogger->created($quote);

            return $quote->load(['items.taxes.taxCode', 'items.rfqItem', 'documents']);
        });
    }

    /**
     * @param Collection<int, array<string, mixed>> $itemsPayload
     * @param Collection<int, RfqItem> $rfqItems
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildLineInputs(Collection $itemsPayload, Collection $rfqItems, string $currency): array
    {
        $lineInputs = [];
        $totalQuantity = 0.0;

        foreach ($itemsPayload as $item) {
            $rfqItemId = (int) $item['rfq_item_id'];
            $rfqItem = $rfqItems->get($rfqItemId);

            if ($rfqItem === null) {
                throw ValidationException::withMessages([
                    'items' => ["RFQ item {$rfqItemId} does not belong to this RFQ."],
                ]);
            }

            $lineCurrency = strtoupper((string) ($item['currency'] ?? $currency));

            if ($lineCurrency !== $currency) {
                throw ValidationException::withMessages([
                    'items' => ['All quote items must use the same currency.'],
                ]);
            }

            $quantity = (float) ($rfqItem->quantity ?? 0);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => ["RFQ item {$rfqItemId} is missing a valid quantity."],
                ]);
            }

            $totalQuantity += $quantity;

            $taxIds = array_values(array_filter(
                array_map('intval', $item['tax_code_ids'] ?? []),
                static fn (int $value): bool => $value > 0
            ));

            $line = [
                'key' => $rfqItemId,
                'quantity' => $quantity,
                'tax_code_ids' => $taxIds,
            ];

            if (array_key_exists('unit_price_minor', $item) && $item['unit_price_minor'] !== null) {
                $line['unit_price_minor'] = (int) $item['unit_price_minor'];
            } else {
                $line['unit_price'] = (float) ($item['unit_price'] ?? 0);
            }

            $lineInputs[] = $line;
        }

        return [$lineInputs, $totalQuantity];
    }
}

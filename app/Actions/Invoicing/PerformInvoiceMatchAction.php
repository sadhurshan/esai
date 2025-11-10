<?php

namespace App\Actions\Invoicing;

use App\Enums\MoneyRoundRule;
use App\Exceptions\FxRateNotFoundException;
use App\Models\CompanyMoneySetting;
use App\Models\Currency;
use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceMatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Notifications\InvoiceMatchResultNotification;
use App\Services\FxService;
use App\Support\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PerformInvoiceMatchAction
{
    private const PRICE_TOLERANCE = 0.01;

    /** @var array<string, int> */
    private array $minorUnitCache = [];

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly FxService $fxService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function execute(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'purchaseOrder.lines']);

        $purchaseOrder = $invoice->purchaseOrder;
        $poLines = $purchaseOrder?->lines->keyBy('id') ?? collect();
        $grnLines = $this->loadGoodsReceiptLines($invoice);

        $roundRule = $this->resolveRoundRule($invoice);
        $matchDate = $this->resolveMatchDate($purchaseOrder);

        return $this->db->transaction(function () use ($invoice, $purchaseOrder, $poLines, $grnLines, $roundRule, $matchDate): array {
            InvoiceMatch::query()->where('invoice_id', $invoice->id)->delete();

            $summary = [
                'matched' => 0,
                'qty_mismatch' => 0,
                'price_mismatch' => 0,
                'unmatched' => 0,
            ];

            $mismatches = [];

            foreach ($invoice->lines as $line) {
                $poLine = $poLines->get($line->po_line_id);
                $relatedGrnLines = $grnLines->get($line->po_line_id, collect());

                $result = 'unmatched';
                $priceContext = [];
                $details = $this->baseDetails($invoice, $line, $poLine, $relatedGrnLines);

                if ($poLine === null) {
                    $result = 'unmatched';
                    $details['reason'] = 'purchase_order_line_missing';
                } else {
                    $priceContext = $this->evaluatePrice($line, $poLine, $purchaseOrder, $matchDate, $roundRule);
                    $priceMismatch = $priceContext['mismatch'];
                    $qtyMismatch = $this->quantityMismatch($line, $relatedGrnLines);

                    $details = $this->baseDetails($invoice, $line, $poLine, $relatedGrnLines, $priceContext);

                    if (! $qtyMismatch && ! $priceMismatch) {
                        $result = 'matched';
                        $details['reason'] = 'matched';
                    } elseif ($qtyMismatch) {
                        $result = 'qty_mismatch';
                        $details['reason'] = 'quantity_difference';
                    } elseif ($priceMismatch) {
                        $result = 'price_mismatch';
                        $details['reason'] = ($priceContext['fx_rate_missing'] ?? false)
                            ? 'fx_rate_unavailable'
                            : 'price_difference';
                    }
                }

                InvoiceMatch::create([
                    'invoice_id' => $invoice->id,
                    'purchase_order_id' => $purchaseOrder?->id,
                    'goods_receipt_note_id' => $relatedGrnLines->first()?->goods_receipt_note_id,
                    'result' => $result,
                    'details' => $details,
                ]);

                $summary[$result]++;

                if ($result !== 'matched') {
                    $mismatches[] = $details;
                }
            }

            if ($mismatches !== []) {
                $this->notifyFinanceTeam($invoice, $summary, $mismatches);
            }

            return $summary;
        });
    }

    /**
     * @return array{
     *     mismatch: bool,
     *     invoice_currency: string,
     *     po_currency: string,
     *     comparison_currency: string,
     *     po_unit_price_comparison: float,
     *     converted_invoice_unit_price: float,
     *     fx_rate_used: float|null,
     *     fx_rate_missing: bool
     * }
     */
    private function evaluatePrice(
        InvoiceLine $invoiceLine,
        PurchaseOrderLine $poLine,
        ?PurchaseOrder $purchaseOrder,
        Carbon $matchDate,
        MoneyRoundRule $roundRule
    ): array {
        $comparisonCurrency = strtoupper($poLine->currency ?? $purchaseOrder?->currency ?? $invoiceLine->currency ?? 'USD');

        $poMoney = $this->resolveLineMoney($poLine, $comparisonCurrency, $roundRule);
        if ($poMoney->currency() !== $comparisonCurrency) {
            $poMoney = $this->fxService->convert($poMoney, $comparisonCurrency, $matchDate, $roundRule);
        }

        $invoiceMoney = $this->resolveLineMoney($invoiceLine, $invoiceLine->invoice?->currency ?? $comparisonCurrency, $roundRule);
        $invoiceSourceCurrency = $invoiceMoney->currency();

        $convertedInvoiceMoney = $invoiceMoney;
        $fxRateUsed = 1.0;
        $fxRateMissing = false;

        if ($invoiceSourceCurrency !== $comparisonCurrency) {
            try {
                $fxRateUsed = (float) $this->fxService->getRate($invoiceSourceCurrency, $comparisonCurrency, $matchDate);
                $convertedInvoiceMoney = $this->fxService->convert($invoiceMoney, $comparisonCurrency, $matchDate, $roundRule);
            } catch (FxRateNotFoundException $exception) {
                $fxRateMissing = true;
                $fxRateUsed = null;
            }
        }

        $comparisonMinor = $this->minorUnit($comparisonCurrency);
        $poPriceComparison = (float) $poMoney->toDecimal($comparisonMinor);
        $convertedInvoicePrice = $convertedInvoiceMoney->currency() === $comparisonCurrency
            ? (float) $convertedInvoiceMoney->toDecimal($comparisonMinor)
            : (float) $invoiceMoney->toDecimal($this->minorUnit($invoiceSourceCurrency));

        $poPriceRounded = round($poPriceComparison, $comparisonMinor);
        $convertedPriceRounded = round($convertedInvoicePrice, $comparisonMinor);

        $mismatch = $fxRateMissing || abs($poPriceRounded - $convertedPriceRounded) > self::PRICE_TOLERANCE;

        return [
            'mismatch' => $mismatch,
            'invoice_currency' => $invoiceSourceCurrency,
            'po_currency' => strtoupper($poLine->currency ?? $comparisonCurrency),
            'comparison_currency' => $comparisonCurrency,
            'po_unit_price_comparison' => $poPriceRounded,
            'converted_invoice_unit_price' => $convertedPriceRounded,
            'fx_rate_used' => $fxRateUsed,
            'fx_rate_missing' => $fxRateMissing,
        ];
    }

    /**
     * @param Collection<int, GoodsReceiptLine> $grnLines
     */
    private function quantityMismatch(InvoiceLine $line, Collection $grnLines): bool
    {
        $receivedQty = (int) $grnLines->sum('received_qty');

        return (int) $line->quantity !== $receivedQty;
    }

    /**
     * @param Collection<int, GoodsReceiptLine> $grnLines
     */
    private function baseDetails(
        Invoice $invoice,
        InvoiceLine $line,
        ?PurchaseOrderLine $poLine,
        Collection $grnLines,
        array $priceContext = []
    ): array {
        $details = [
            'invoice_id' => $invoice->id,
            'invoice_line_id' => $line->id,
            'invoice_quantity' => (int) $line->quantity,
            'invoice_unit_price' => (float) $line->unit_price,
            'po_line_id' => $poLine?->id,
            'po_quantity' => $poLine?->quantity,
            'po_unit_price' => $poLine?->unit_price,
            'received_quantity' => (int) $grnLines->sum('received_qty'),
        ];

        $details['invoice_currency'] = $priceContext['invoice_currency'] ?? strtoupper($line->currency ?? $invoice->currency ?? 'USD');
        $details['po_currency'] = $priceContext['po_currency'] ?? ($poLine?->currency ?? $invoice->purchaseOrder?->currency);
        $details['comparison_currency'] = $priceContext['comparison_currency'] ?? $details['po_currency'];

        if (array_key_exists('po_unit_price_comparison', $priceContext)) {
            $details['po_unit_price_comparison'] = $priceContext['po_unit_price_comparison'];
        }

        if (array_key_exists('converted_invoice_unit_price', $priceContext)) {
            $details['converted_invoice_unit_price'] = $priceContext['converted_invoice_unit_price'];
        }

        if (array_key_exists('fx_rate_used', $priceContext) && $priceContext['fx_rate_used'] !== null) {
            $details['fx_rate_used'] = $priceContext['fx_rate_used'];
        }

        if (($priceContext['fx_rate_missing'] ?? false) === true) {
            $details['fx_rate_missing'] = true;
        }

        return $details;
    }

    /**
     * @return Collection<int, Collection<int, GoodsReceiptLine>>
     */
    private function loadGoodsReceiptLines(Invoice $invoice): Collection
    {
        $poLineIds = $invoice->lines
            ->pluck('po_line_id')
            ->filter()
            ->values();

        if ($poLineIds->isEmpty()) {
            return collect();
        }

        return GoodsReceiptLine::query()
            ->whereIn('purchase_order_line_id', $poLineIds)
            ->get()
            ->groupBy('purchase_order_line_id');
    }

    private function resolveLineMoney(InvoiceLine|PurchaseOrderLine $line, string $fallbackCurrency, MoneyRoundRule $roundRule): Money
    {
        $currency = strtoupper($line->currency ?? $fallbackCurrency);
        $minorUnit = $this->minorUnit($currency);

        if ($line->unit_price_minor !== null) {
            return Money::fromMinor((int) $line->unit_price_minor, $currency);
        }

        return Money::fromDecimal((float) $line->unit_price, $currency, $minorUnit, $roundRule);
    }

    private function resolveRoundRule(Invoice $invoice): MoneyRoundRule
    {
        $companyId = $invoice->company_id;

        if ($companyId === null) {
            return MoneyRoundRule::HalfUp;
        }

        $setting = CompanyMoneySetting::query()->where('company_id', $companyId)->first();

        if ($setting?->price_round_rule !== null) {
            return MoneyRoundRule::from($setting->price_round_rule);
        }

        return MoneyRoundRule::HalfUp;
    }

    private function resolveMatchDate(?PurchaseOrder $purchaseOrder): Carbon
    {
        if ($purchaseOrder?->ordered_at instanceof Carbon) {
            return $purchaseOrder->ordered_at->copy();
        }

        if ($purchaseOrder?->ordered_at !== null) {
            return Carbon::parse($purchaseOrder->ordered_at);
        }

        if ($purchaseOrder?->created_at instanceof Carbon) {
            return $purchaseOrder->created_at->copy();
        }

        if ($purchaseOrder?->created_at !== null) {
            return Carbon::parse($purchaseOrder->created_at);
        }

        return now();
    }

    private function minorUnit(string $currency): int
    {
        $currency = strtoupper($currency);

        if (! array_key_exists($currency, $this->minorUnitCache)) {
            $record = Currency::query()->where('code', $currency)->first();
            $this->minorUnitCache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) $this->minorUnitCache[$currency];
    }

    private function notifyFinanceTeam(Invoice $invoice, array $summary, array $mismatches): void
    {
        $recipients = User::query()
            ->where('company_id', $invoice->company_id)
            ->whereIn('role', ['finance', 'buyer_admin'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new InvoiceMatchResultNotification($invoice, $summary, $mismatches));
    }
}

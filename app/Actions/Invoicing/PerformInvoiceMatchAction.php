<?php

namespace App\Actions\Invoicing;

use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceMatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\InvoiceMatchResultNotification;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PerformInvoiceMatchAction
{
    private const PRICE_TOLERANCE = 0.01;

    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @return array<string, int>
     */
    public function execute(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'purchaseOrder.lines']);

        $po = $invoice->purchaseOrder;

        $poLines = $po?->lines->keyBy('id') ?? collect();

        $grnLines = $this->loadGoodsReceiptLines($invoice);

        return $this->db->transaction(function () use ($invoice, $po, $poLines, $grnLines): array {
            InvoiceMatch::query()->where('invoice_id', $invoice->id)->delete();

            $summary = [
                'matched' => 0,
                'qty_mismatch' => 0,
                'price_mismatch' => 0,
                'unmatched' => 0,
            ];

            $mismatches = [];

            /** @var InvoiceLine $line */
            foreach ($invoice->lines as $line) {
                $poLine = $poLines->get($line->po_line_id);
                $relatedGrnLines = $grnLines->get($line->po_line_id, collect());

                $result = 'unmatched';
                $details = $this->baseDetails($invoice, $line, $poLine, $relatedGrnLines);

                if ($poLine === null) {
                    $result = 'unmatched';
                    $details['reason'] = 'purchase_order_line_missing';
                } else {
                    $priceMismatch = $this->priceMismatch($line, $poLine);
                    $qtyMismatch = $this->quantityMismatch($line, $relatedGrnLines);

                    if (! $qtyMismatch && ! $priceMismatch) {
                        $result = 'matched';
                        $details['reason'] = 'matched';
                    } elseif ($qtyMismatch) {
                        $result = 'qty_mismatch';
                        $details['reason'] = 'quantity_difference';
                    } elseif ($priceMismatch) {
                        $result = 'price_mismatch';
                        $details['reason'] = 'price_difference';
                    }
                }

                InvoiceMatch::create([
                    'invoice_id' => $invoice->id,
                    'purchase_order_id' => $po?->id,
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

    private function priceMismatch(InvoiceLine $line, $poLine): bool
    {
        $poPrice = (float) $poLine->unit_price;
        $invoicePrice = (float) $line->unit_price;

        return abs($poPrice - $invoicePrice) > self::PRICE_TOLERANCE;
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
    private function baseDetails(Invoice $invoice, InvoiceLine $line, $poLine, Collection $grnLines): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_line_id' => $line->id,
            'invoice_quantity' => (int) $line->quantity,
            'invoice_unit_price' => (float) $line->unit_price,
            'po_line_id' => $poLine?->id,
            'po_quantity' => $poLine?->quantity,
            'po_unit_price' => $poLine?->unit_price,
            'received_quantity' => (int) $grnLines->sum('received_qty'),
        ];
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

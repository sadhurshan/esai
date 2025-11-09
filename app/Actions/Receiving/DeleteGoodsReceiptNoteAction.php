<?php

namespace App\Actions\Receiving;

use App\Models\Document;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrderLine;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeleteGoodsReceiptNoteAction
{
    private ?bool $poLineReceivingColumns = null;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(GoodsReceiptNote $note): void
    {
        if ($note->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending goods receipt notes can be deleted.'],
            ]);
        }

        $before = $note->toArray();

        $this->db->transaction(function () use ($note, $before): void {
            $lines = $note->lines()->get();
            $lineIds = $lines->pluck('id');
            $attachmentIds = collect($lines->pluck('attachment_ids')->flatten())->filter();

            if ($attachmentIds->isNotEmpty()) {
                Document::query()
                    ->whereIn('id', $attachmentIds)
                    ->where('documentable_type', GoodsReceiptLine::class)
                    ->delete();
            }

            foreach ($lines as $line) {
                $line->delete();
            }

            $note->delete();

            $this->syncImpactedPurchaseOrderLines($lines);

            $this->auditLogger->deleted($note, $before);
        });
    }

    /**
     * @param Collection<int, GoodsReceiptLine> $lines
     */
    private function syncImpactedPurchaseOrderLines(Collection $lines): void
    {
        if ($lines === null || $lines->isEmpty()) {
            return;
        }

        if (! $this->hasReceivingColumns()) {
            // TODO: clarify with spec - po_lines should expose receiving columns for GRN sync.
            return;
        }

        $poLineIds = $lines
            ->pluck('purchase_order_line_id')
            ->unique()
            ->values();

        foreach ($poLineIds as $poLineId) {
            $poLine = PurchaseOrderLine::query()->find($poLineId);

            if ($poLine === null) {
                continue;
            }

            $totals = GoodsReceiptLine::query()
                ->where('purchase_order_line_id', $poLine->id)
                ->selectRaw('COALESCE(SUM(received_qty), 0) as received_qty, COALESCE(SUM(rejected_qty), 0) as rejected_qty')
                ->first();

            $received = (int) ($totals?->received_qty ?? 0);
            $rejected = (int) ($totals?->rejected_qty ?? 0);

            $status = match (true) {
                $rejected > 0 => 'ncr_raised',
                $received > 0 => 'received',
                default => 'open',
            };

            $poLine->forceFill([
                'received_qty' => $received,
                'receiving_status' => $status,
            ]);

            $poLine->save();
        }
    }

    private function hasReceivingColumns(): bool
    {
        if ($this->poLineReceivingColumns !== null) {
            return $this->poLineReceivingColumns;
        }

        $this->poLineReceivingColumns = Schema::hasColumn('po_lines', 'received_qty')
            && Schema::hasColumn('po_lines', 'receiving_status');

        return $this->poLineReceivingColumns;
    }
}

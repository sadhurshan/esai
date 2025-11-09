<?php

namespace App\Actions\Receiving;

use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreateGoodsReceiptNoteAction
{
    private ?bool $poLineReceivingColumns = null;

    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{note: GoodsReceiptNote, lines: EloquentCollection<int, GoodsReceiptLine>}
     *
     * @throws ValidationException
     */
    public function execute(User $user, PurchaseOrder $purchaseOrder, array $payload): array
    {
        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User company context missing.'],
            ]);
        }

        if ((int) $purchaseOrder->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not found for this company.'],
            ]);
        }

        $inspectorId = $payload['inspected_by_id'] ?? $user->id;

        if ($inspectorId !== null) {
            $validInspector = User::query()
                ->whereKey($inspectorId)
                ->where('company_id', $companyId)
                ->exists();

            if (! $validInspector) {
                throw ValidationException::withMessages([
                    'inspected_by_id' => ['Inspector must belong to the same company.'],
                ]);
            }
        }

        $linesPayload = collect($payload['lines']);

        if ($linesPayload->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => ['At least one line is required.'],
            ]);
        }

        return $this->db->transaction(function () use ($companyId, $purchaseOrder, $payload, $linesPayload, $inspectorId, $user): array {
            $note = GoodsReceiptNote::create([
                'company_id' => $companyId,
                'purchase_order_id' => $purchaseOrder->id,
                'number' => $payload['number'],
                'inspected_by_id' => $inspectorId,
                'inspected_at' => $payload['inspected_at'] ?? now(),
                'status' => 'pending',
            ]);

            $createdLines = new EloquentCollection();
            $hasRejection = false;

            foreach ($linesPayload as $linePayload) {
                $poLine = $this->resolvePurchaseOrderLine($purchaseOrder, $linePayload['purchase_order_line_id']);

                $receivedQty = (int) round($linePayload['received_qty']);
                $acceptedQty = (int) round($linePayload['accepted_qty']);
                $rejectedQty = (int) round($linePayload['rejected_qty']);

                $this->assertQtyConstraints($poLine, $receivedQty, $acceptedQty, $rejectedQty);

                /** @var array<int, UploadedFile>|null $attachments */
                $attachments = $linePayload['attachments'] ?? null;

                $grnLine = GoodsReceiptLine::create([
                    'goods_receipt_note_id' => $note->id,
                    'purchase_order_line_id' => $poLine->id,
                    'received_qty' => $receivedQty,
                    'accepted_qty' => $acceptedQty,
                    'rejected_qty' => $rejectedQty,
                    'defect_notes' => $linePayload['defect_notes'] ?? null,
                    'attachment_ids' => [],
                ]);

                $attachmentIds = $this->storeAttachments($attachments, $companyId, $grnLine);

                if ($attachmentIds !== []) {
                    $grnLine->attachment_ids = $attachmentIds;
                    $grnLine->save();
                }

                $this->syncPurchaseOrderLineReceiving($poLine);

                $createdLines->add($grnLine);

                if ($rejectedQty > 0) {
                    $hasRejection = true;
                }
            }

            if ($hasRejection) {
                $note->status = 'ncr_raised';
            } elseif ($createdLines->sum('received_qty') > 0) {
                $note->status = 'complete';
            } else {
                $note->status = 'pending';
            }
            $note->save();

            $this->auditLogger->created($note);

            return [
                'note' => $note,
                'lines' => $createdLines,
            ];
        });
    }

    /**
     * @param array<int, UploadedFile>|null $attachments
     * @return array<int, int>
     */
    private function storeAttachments(?array $attachments, int $companyId, GoodsReceiptLine $line): array
    {
        if ($attachments === null || $attachments === []) {
            return [];
        }

        $documents = [];

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $document = $this->documentStorer->store(
                $file,
                'grn',
                $companyId,
                $line->getMorphClass(),
                $line->id
            );

            $documents[] = $document->id;
        }

        return $documents;
    }

    private function resolvePurchaseOrderLine(PurchaseOrder $purchaseOrder, int $lineId): PurchaseOrderLine
    {
        $poLine = $purchaseOrder->lines()
            ->whereKey($lineId)
            ->first();

        if ($poLine === null) {
            throw ValidationException::withMessages([
                'lines' => ["Purchase order line {$lineId} is not linked to this purchase order."],
            ]);
        }

        return $poLine;
    }

    private function assertQtyConstraints(PurchaseOrderLine $line, int $receivedQty, int $acceptedQty, int $rejectedQty): void
    {
        if ($acceptedQty + $rejectedQty !== $receivedQty) {
            throw ValidationException::withMessages([
                'lines' => ['Accepted and rejected quantities must equal received quantity.'],
            ]);
        }

        if ($receivedQty <= 0) {
            throw ValidationException::withMessages([
                'lines' => ['Received quantity must be greater than zero.'],
            ]);
        }

        $alreadyReceived = GoodsReceiptLine::query()
            ->where('purchase_order_line_id', $line->id)
            ->sum('received_qty');

        $remaining = max(0, (int) $line->quantity - (int) $alreadyReceived);

        if ($receivedQty > $remaining) {
            throw ValidationException::withMessages([
                'lines' => ['Received quantity exceeds remaining open quantity for the PO line.'],
            ]);
        }
    }

    private function syncPurchaseOrderLineReceiving(PurchaseOrderLine $line): void
    {
        if (! $this->hasReceivingColumns()) {
            // TODO: clarify with spec - po_lines should expose receiving columns for GRN sync.
            return;
        }

        $totals = GoodsReceiptLine::query()
            ->selectRaw('COALESCE(SUM(received_qty), 0) as received_qty, COALESCE(SUM(rejected_qty), 0) as rejected_qty')
            ->where('purchase_order_line_id', $line->id)
            ->first();

        $received = (int) ($totals?->received_qty ?? 0);
        $rejected = (int) ($totals?->rejected_qty ?? 0);

        $status = match (true) {
            $rejected > 0 => 'ncr_raised',
            $received > 0 => 'received',
            default => 'open',
        };

        $line->forceFill([
            'received_qty' => $received,
            'receiving_status' => $status,
        ]);

        $line->save();
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

<?php

namespace App\Actions\Receiving;

use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Ncr;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;

class CloseNcrAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param array{disposition?:string|null} $payload
     */
    public function execute(User $user, Ncr $ncr, array $payload): Ncr
    {
        return $this->db->transaction(function () use ($user, $ncr, $payload): Ncr {
            if ($ncr->status !== 'closed') {
                $ncr->status = 'closed';
                $ncr->disposition = $payload['disposition'] ?? $ncr->disposition;
                $ncr->save();

                $this->auditLogger->updated($ncr);
            }

            $note = $ncr->goodsReceiptNote()->first();
            $line = GoodsReceiptLine::query()
                ->where('goods_receipt_note_id', $ncr->goods_receipt_note_id)
                ->where('purchase_order_line_id', $ncr->purchase_order_line_id)
                ->first();

            if ($line instanceof GoodsReceiptLine) {
                $hasOpenLineNcr = $line->ncrs()->where('status', 'open')->exists();

                if (! $hasOpenLineNcr && $line->ncr_flag) {
                    $line->forceFill(['ncr_flag' => false])->save();
                }
            }

            if ($note instanceof GoodsReceiptNote) {
                $hasOpenNoteNcr = $note->ncrs()->where('status', 'open')->exists();

                if (! $hasOpenNoteNcr && $note->status === 'ncr_raised') {
                    $note->status = 'complete';
                    $note->save();
                }
            }

            return $ncr->fresh(['raisedBy']);
        });
    }
}

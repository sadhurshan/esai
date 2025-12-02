<?php

namespace App\Actions\Receiving;

use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Ncr;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;

class CreateNcrAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param array{reason:string, disposition?:string|null, documents?:array<int, int>} $payload
     */
    public function execute(User $user, GoodsReceiptNote $note, GoodsReceiptLine $line, array $payload): Ncr
    {
        return $this->db->transaction(function () use ($user, $note, $line, $payload): Ncr {
            $documents = collect($payload['documents'] ?? [])
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn ($id) => $id > 0)
                ->values()
                ->all();

            $ncr = Ncr::create([
                'company_id' => $note->company_id,
                'goods_receipt_note_id' => $note->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'raised_by_id' => $user->id,
                'status' => 'open',
                'disposition' => $payload['disposition'] ?? null,
                'reason' => $payload['reason'],
                'documents_json' => $documents === [] ? null : $documents,
            ]);

            if (! $line->ncr_flag) {
                $line->forceFill(['ncr_flag' => true])->save();
            }

            if ($note->status !== 'ncr_raised') {
                $note->status = 'ncr_raised';
                $note->save();
            }

            $this->auditLogger->created($ncr);

            return $ncr;
        });
    }
}

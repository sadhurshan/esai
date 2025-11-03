<?php

namespace App\Actions\Rfq;

use App\Models\RFQ;
use App\Models\RfqItem;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Contracts\Database\Transactions\TransactionManager as TransactionManagerContract;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Models\User;

class CreateRfqAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
    ) {}

    /**
     * @param array{title: string, type: string, material?: string|null, method?: string|null, tolerance_finish?: string|null, incoterm?: string|null, currency?: string|null, open_bidding?: bool, publish_at?: string|null, due_at?: string|null, close_at?: string|null, items: array<int, array{part_name: string, quantity: int, uom?: string|null, spec?: string|null, target_price?: string|null}>} $data
     */
    public function execute(User $user, array $data, ?UploadedFile $cadFile = null): RFQ
    {
        return DB::transaction(function () use ($user, $data, $cadFile): RFQ {
            $rfq = RFQ::create([
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'title' => $data['title'],
                'type' => $data['type'],
                'material' => $data['material'] ?? null,
                'method' => $data['method'] ?? null,
                'tolerance_finish' => $data['tolerance_finish'] ?? null,
                'incoterm' => $data['incoterm'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'open_bidding' => (bool) ($data['open_bidding'] ?? false),
                'publish_at' => $data['publish_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'close_at' => $data['close_at'] ?? null,
                'status' => 'draft',
                'version' => 1,
            ]);

            $lineNo = 1;
            foreach ($data['items'] as $item) {
                RfqItem::create([
                    'rfq_id' => $rfq->id,
                    'line_no' => $lineNo++,
                    'part_name' => $item['part_name'],
                    'spec' => $item['spec'] ?? null,
                    'quantity' => $item['quantity'],
                    'uom' => $item['uom'] ?? 'pcs',
                    'target_price' => $item['target_price'] ?? null,
                ]);
            }

            if ($cadFile) {
                $this->documentStorer->store(
                    $cadFile,
                    'rfq',
                    $rfq->company_id,
                    $rfq->getMorphClass(),
                    $rfq->id
                );
            }

            $this->auditLogger->created($rfq);

            return $rfq->load(['items']);
        });
    }
}

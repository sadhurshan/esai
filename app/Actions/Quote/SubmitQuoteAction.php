<?php

namespace App\Actions\Quote;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Support\Documents\DocumentStorer;

class SubmitQuoteAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer
    ) {}

    /**
     * @param array{company_id: int, rfq_id: int, supplier_id: int, submitted_by: int|null, currency: string, unit_price: string|float, min_order_qty?: int|null, lead_time_days: int, note?: string|null, status?: string|null, revision_no?: int|null, items: array<int, array{rfq_item_id: int, unit_price: string|float, lead_time_days: int, note?: string|null}>} $data
     */
    public function execute(array $data, ?UploadedFile $attachment = null): Quote
    {
        return DB::transaction(function () use ($data, $attachment): Quote {
            $quote = Quote::create([
                'company_id' => $data['company_id'],
                'rfq_id' => $data['rfq_id'],
                'supplier_id' => $data['supplier_id'],
                'submitted_by' => $data['submitted_by'] ?? null,
                'currency' => $data['currency'],
                'unit_price' => $data['unit_price'],
                'min_order_qty' => $data['min_order_qty'] ?? null,
                'lead_time_days' => $data['lead_time_days'],
                'note' => $data['note'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'revision_no' => $data['revision_no'] ?? 1,
            ]);

            foreach ($data['items'] as $item) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'rfq_item_id' => $item['rfq_item_id'],
                    'unit_price' => $item['unit_price'],
                    'lead_time_days' => $item['lead_time_days'],
                    'note' => $item['note'] ?? null,
                ]);
            }

            if ($attachment) {
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

            return $quote->load(['items', 'documents']);
        });
    }
}

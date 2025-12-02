<?php

namespace App\Actions\Rfq;

use App\Actions\Rfq\Concerns\ManagesRfqAwardState;
use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuoteItemsAction;
use App\Enums\RfqItemAwardStatus;
use App\Exceptions\RfqAwardException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AwardLineItemsAction
{
    use ManagesRfqAwardState;

    private const SUPPLIER_NOTIFICATION_ROLES = ['supplier_admin', 'supplier_estimator'];

    public function __construct(
        private readonly CreatePurchaseOrderFromQuoteItemsAction $createPurchaseOrderFromQuoteItemsAction,
        private readonly NotificationService $notifications,
        protected readonly AuditLogger $auditLogger,
        protected readonly RfqVersionService $rfqVersionService,
    ) {
    }

    /**
     * @param array<int, array{rfq_item_id:int, quote_item_id:int}> $awards
     * @return Collection<int, PurchaseOrder>
     *
     * @throws ValidationException
     */
    public function execute(RFQ $rfq, array $awards, User $user, bool $createPurchaseOrders = true): Collection
    {
        $this->assertRfqEligible($rfq);

        $awardRows = $this->normalizeAwards($rfq, $awards);

        if ($awardRows->isEmpty()) {
            throw ValidationException::withMessages([
                'awards' => ['At least one RFQ item must be awarded.'],
            ]);
        }

        $rfqItemIds = $awardRows->pluck('rfq_item_id')->unique()->all();

        $activeAwardsExist = RfqItemAward::query()
            ->whereIn('rfq_item_id', $rfqItemIds)
            ->where('status', RfqItemAwardStatus::Awarded)
            ->exists();

        if ($activeAwardsExist) {
            throw new RfqAwardException('RFQ item already awarded.', 409);
        }

        $winnerNotifications = [];
        $loserItems = [];
        $winnerSupplierIds = [];
        $winnerQuoteItemIds = [];
        $purchaseOrders = [];

        DB::transaction(function () use (
            $rfq,
            $awardRows,
            $user,
            &$winnerNotifications,
            &$loserItems,
            &$winnerSupplierIds,
            &$winnerQuoteItemIds,
            &$purchaseOrders,
            $rfqItemIds,
            $createPurchaseOrders
        ): void {
            $groupedBySupplier = $awardRows->groupBy('supplier_id');
            $awardedAt = now();

            /** @var array<int, int> $rfqItemIds */
            foreach ($groupedBySupplier as $supplierId => $supplierRows) {
                /** @var Supplier $supplier */
                $supplier = $supplierRows->first()['supplier'];
                $quoteItemIds = $supplierRows->pluck('quote_item_id')->all();

                $po = null;
                $lines = [];

                if ($createPurchaseOrders) {
                    $result = $this->createPurchaseOrderFromQuoteItemsAction->execute(
                        $rfq,
                        $supplier,
                        $quoteItemIds
                    );

                    /** @var PurchaseOrder $poInstance */
                    $poInstance = $result['po'];
                    /** @var array<int, PurchaseOrderLine> $linesInstance */
                    $linesInstance = $result['lines'];

                    $po = $poInstance;
                    $lines = $linesInstance;

                    $purchaseOrders[$po->id] = $po;
                }

                foreach ($supplierRows as $row) {
                    /** @var Quote $quote */
                    $quote = $row['quote'];
                    /** @var QuoteItem $quoteItem */
                    $quoteItem = $row['quote_item'];
                    $awardedQty = (int) ($row['awarded_qty'] ?? ($quoteItem->rfqItem->quantity ?? 1));

                    $award = RfqItemAward::create([
                        'company_id' => $rfq->company_id,
                        'rfq_id' => $rfq->id,
                        'rfq_item_id' => $row['rfq_item_id'],
                        'supplier_id' => $supplierId,
                        'quote_id' => $quote->id,
                        'quote_item_id' => $quoteItem->id,
                        'awarded_qty' => $awardedQty,
                        'po_id' => $po?->id,
                        'awarded_by' => $user->id,
                        'awarded_at' => $awardedAt,
                        'status' => RfqItemAwardStatus::Awarded,
                    ]);

                    if ($po instanceof PurchaseOrder) {
                        $poLine = $lines[$row['rfq_item_id']] ?? $po->lines()
                            ->where('rfq_item_id', $row['rfq_item_id'])
                            ->orderByDesc('id')
                            ->first();

                        if ($poLine instanceof PurchaseOrderLine) {
                            $poLine->rfq_item_award_id = $award->id;
                            $poLine->quantity = $awardedQty > 0 ? $awardedQty : $poLine->quantity;
                            $poLine->save();
                        }
                    }

                    $this->auditLogger->created($award, [
                        'rfq_item_id' => $row['rfq_item_id'],
                        'po_id' => $po?->id,
                    ]);

                    $this->updateQuoteItemStatus($quoteItem, 'awarded');

                    $winnerSupplierIds[] = $supplierId;
                    $winnerQuoteItemIds[] = $quoteItem->id;

                    $winnerNotifications[$supplierId]['items'][] = $row['rfq_item_id'];
                    $winnerNotifications[$supplierId]['supplier'] = $supplier;

                    if ($po instanceof PurchaseOrder) {
                        $winnerNotifications[$supplierId]['po'] = $po;
                    }
                }
            }

            $winnerQuoteItemIds = array_values(array_unique($winnerQuoteItemIds));
            $winnerSupplierIds = array_values(array_unique($winnerSupplierIds));

            $losingQuoteItems = CompanyContext::bypass(fn () => QuoteItem::query()
                ->with('quote')
                ->whereIn('rfq_item_id', $rfqItemIds)
                ->whereNotIn('id', $winnerQuoteItemIds)
                ->get());

            foreach ($losingQuoteItems as $quoteItem) {
                $this->updateQuoteItemStatus($quoteItem, 'rejected');

                $supplierId = $quoteItem->quote?->supplier_id;
                if ($supplierId !== null) {
                    $loserItems[$supplierId][] = $quoteItem->rfq_item_id;
                }
            }

            $this->refreshQuoteStatuses($rfq);
            $this->refreshRfqState($rfq);
        });

        $winnerSupplierIds = array_values(array_unique($winnerSupplierIds));

        $filteredLoserItems = [];
        foreach ($loserItems as $supplierId => $itemIds) {
            $uniqueItems = array_values(array_unique($itemIds));
            if ($uniqueItems === [] || in_array($supplierId, $winnerSupplierIds, true)) {
                continue;
            }

            $filteredLoserItems[$supplierId] = $uniqueItems;
        }

        $this->notifySuppliers($rfq, $winnerNotifications, $filteredLoserItems);

        return collect(array_values($purchaseOrders))->map(function (PurchaseOrder $po): PurchaseOrder {
            return $po->loadMissing(['lines.rfqItem', 'quote.supplier', 'rfq']);
        })->values();
    }

    private function assertRfqEligible(RFQ $rfq): void
    {
        if (in_array($rfq->status, ['cancelled', 'awarded', 'closed'], true)) {
            throw new RfqAwardException('RFQ is not open for awards.', 409);
        }

        $deadline = $rfq->deadline_at ?? $rfq->due_at;

        if ($deadline !== null && now()->greaterThan($deadline)) {
            throw new RfqAwardException('Deadline passed', 400);
        }
    }

    /**
     * @param array<int, array{rfq_item_id:int, quote_item_id:int}> $awards
     * @return Collection<int, array{
     *     rfq_item_id:int,
     *     quote_item_id:int,
     *     quote:Quote,
     *     quote_item:QuoteItem,
     *     supplier:Supplier,
     *     supplier_id:int,
     * }>
     */
    private function normalizeAwards(RFQ $rfq, array $awards): Collection
    {
        $payload = collect($awards)->values();

        $rfqItemIds = $payload->pluck('rfq_item_id')->unique()->all();
        $quoteItemIds = $payload->pluck('quote_item_id')->unique()->all();

        /** @var EloquentCollection<int, RfqItem> $rfqItems */
        $rfqItems = RfqItem::query()
            ->where('rfq_id', $rfq->id)
            ->whereIn('id', $rfqItemIds)
            ->get()
            ->keyBy('id');

        /** @var EloquentCollection<int, QuoteItem> $quoteItems */
        $quoteItems = CompanyContext::bypass(fn () => QuoteItem::query()
            ->with(['quote.supplier.company', 'rfqItem'])
            ->whereIn('id', $quoteItemIds)
            ->get()
            ->keyBy('id'));

        return $payload->map(function (array $row, int $index) use ($rfq, $rfqItems, $quoteItems) {
            $rfqItemId = (int) ($row['rfq_item_id'] ?? 0);
            $quoteItemId = (int) ($row['quote_item_id'] ?? 0);
            $awardedQtyInput = $row['awarded_qty'] ?? null;

            /** @var RfqItem|null $rfqItem */
            $rfqItem = $rfqItems->get($rfqItemId);
            if ($rfqItem === null) {
                throw ValidationException::withMessages([
                    "awards.$index.rfq_item_id" => ['RFQ item does not belong to this RFQ.'],
                ]);
            }

            /** @var QuoteItem|null $quoteItem */
            $quoteItem = $quoteItems->get($quoteItemId);
            if ($quoteItem === null) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Quote item not found.'],
                ]);
            }

            $quote = $quoteItem->quote;

            if ($quote === null || (int) $quote->rfq_id !== (int) $rfq->id) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Quote item does not belong to this RFQ.'],
                ]);
            }

            if ((int) $quoteItem->rfq_item_id !== $rfqItemId) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Quote item does not match the RFQ line.'],
                ]);
            }

            if (in_array($quote->status, ['withdrawn', 'rejected'], true) || $quote->withdrawn_at !== null) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Selected quote is not eligible for awarding.'],
                ]);
            }

            if (! in_array($quote->status, ['submitted', 'awarded'], true)) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Quote is not in a valid state for awarding.'],
                ]);
            }

            $supplier = $quote->supplier;
            if ($supplier === null) {
                throw ValidationException::withMessages([
                    "awards.$index.quote_item_id" => ['Quote supplier missing.'],
                ]);
            }

            if ($quoteItem->status === 'awarded') {
                throw new RfqAwardException('RFQ item already awarded.', 409);
            }

            $awardedQty = (int) ($awardedQtyInput ?? $rfqItem?->quantity ?? 0);

            if ($awardedQty <= 0) {
                throw ValidationException::withMessages([
                    "awards.$index.awarded_qty" => ['Awarded quantity must be greater than zero.'],
                ]);
            }

            if ($rfqItem !== null && $awardedQty > (int) $rfqItem->quantity) {
                throw ValidationException::withMessages([
                    "awards.$index.awarded_qty" => ['Awarded quantity exceeds RFQ line quantity.'],
                ]);
            }

            return [
                'rfq_item_id' => $rfqItemId,
                'quote_item_id' => $quoteItemId,
                'quote' => $quote,
                'quote_item' => $quoteItem,
                'supplier' => $supplier,
                'supplier_id' => $supplier->id,
                'awarded_qty' => $awardedQty,
            ];
        });
    }

    /**
     * @param array<int, array{items:array<int,int>, supplier:Supplier, po:PurchaseOrder}> $winnerNotifications
     * @param array<int, array<int,int>> $loserItems
     */
    private function notifySuppliers(RFQ $rfq, array $winnerNotifications, array $loserItems): void
    {
        foreach ($winnerNotifications as $supplierId => $payload) {
            /** @var Supplier $supplier */
            $supplier = $payload['supplier'];
            $itemIds = array_values(array_unique($payload['items']));

            $recipients = $this->supplierRecipients($supplier);
            if ($recipients->isEmpty()) {
                continue;
            }

            $po = $payload['po'] ?? null;
            $resourceType = $po instanceof PurchaseOrder ? PurchaseOrder::class : RFQ::class;
            $resourceId = $po instanceof PurchaseOrder ? $po->id : $rfq->id;

            $meta = [
                'rfq_id' => $rfq->id,
                'rfq_item_ids' => $itemIds,
            ];

            if ($po instanceof PurchaseOrder) {
                $meta['po_id'] = $po->id;
                $meta['po_number'] = $po->po_number;
            }

            $this->notifications->send(
                $recipients,
                'rfq_line_awarded',
                'RFQ lines awarded',
                sprintf('RFQ %s awarded line items %s to your company.', $rfq->number ?? $rfq->id, implode(', ', $itemIds)),
                $resourceType,
                $resourceId,
                $meta
            );
        }

        foreach ($loserItems as $supplierId => $itemIds) {
            $supplier = CompanyContext::bypass(fn () => Supplier::query()->with('company')->find($supplierId));

            if (! $supplier instanceof Supplier) {
                continue;
            }

            $recipients = $this->supplierRecipients($supplier);
            if ($recipients->isEmpty()) {
                continue;
            }

            $this->notifications->send(
                $recipients,
                'rfq_line_lost',
                'RFQ lines awarded to another supplier',
                sprintf('RFQ %s awarded line items %s to another supplier.', $rfq->number ?? $rfq->id, implode(', ', $itemIds)),
                RFQ::class,
                $rfq->id,
                [
                    'rfq_id' => $rfq->id,
                    'lost_rfq_item_ids' => $itemIds,
                ]
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function supplierRecipients(Supplier $supplier): Collection
    {
        return User::query()
            ->where('company_id', $supplier->company_id)
            ->whereIn('role', [...self::SUPPLIER_NOTIFICATION_ROLES, 'platform_super', 'platform_support'])
            ->get();
    }
}

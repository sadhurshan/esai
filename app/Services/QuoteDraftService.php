<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RfqItem;
use App\Support\Audit\AuditLogger;
use App\Support\RfqResponseWindowGuard;
use App\Support\CompanyContext;
use App\Support\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteDraftService
{
    public function __construct(
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
        private readonly AuditLogger $auditLogger,
        private readonly RfqResponseWindowGuard $rfqResponseWindowGuard
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function addLine(Quote $quote, array $payload): Quote
    {
        return CompanyContext::bypass(function () use ($quote, $payload): Quote {
            $this->assertDraft($quote);
            $this->rfqResponseWindowGuard->ensureQuoteRfqOpenForResponses($quote, 'add quote lines');

            $rfqItemId = (int) $payload['rfq_item_id'];

            if ($quote->items()->where('rfq_item_id', $rfqItemId)->exists()) {
                throw ValidationException::withMessages([
                    'rfq_item_id' => ['This RFQ line already has a quote entry.'],
                ]);
            }

            $rfqItem = $this->resolveRfqItem($quote, $rfqItemId);

            [$linePayloads, $meta] = $this->buildLinePayloads(
                $quote,
                [],
                [[
                    'rfq_item' => $rfqItem,
                    'unit_price' => $payload['unit_price'] ?? null,
                    'unit_price_minor' => $payload['unit_price_minor'] ?? null,
                    'tax_code_ids' => $payload['tax_code_ids'] ?? [],
                    'attributes' => [
                        'lead_time_days' => (int) $payload['lead_time_days'],
                        'note' => $payload['note'] ?? null,
                        'status' => $payload['status'] ?? 'pending',
                    ],
                ]]
            );

            return $this->persistLineChanges($quote, $linePayloads, $meta);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateLine(Quote $quote, QuoteItem $item, array $payload): Quote
    {
        return CompanyContext::bypass(function () use ($quote, $item, $payload): Quote {
            $this->assertDraft($quote);
            $this->assertQuoteItem($quote, $item);
            $this->rfqResponseWindowGuard->ensureQuoteRfqOpenForResponses($quote, 'update quote lines');

            $overrides = [
                $item->id => [
                    'unit_price' => array_key_exists('unit_price', $payload) ? $payload['unit_price'] : null,
                    'unit_price_minor' => array_key_exists('unit_price_minor', $payload) ? $payload['unit_price_minor'] : null,
                    'tax_code_ids' => array_key_exists('tax_code_ids', $payload) ? ($payload['tax_code_ids'] ?? []) : null,
                    'attributes' => [
                        'lead_time_days' => array_key_exists('lead_time_days', $payload) ? $payload['lead_time_days'] : null,
                        'note' => array_key_exists('note', $payload) ? $payload['note'] : null,
                        'status' => array_key_exists('status', $payload) ? $payload['status'] : null,
                    ],
                ],
            ];

            [$linePayloads, $meta] = $this->buildLinePayloads($quote, $overrides);

            return $this->persistLineChanges($quote, $linePayloads, $meta);
        });
    }

    public function deleteLine(Quote $quote, QuoteItem $item): Quote
    {
        return CompanyContext::bypass(function () use ($quote, $item): Quote {
            $this->assertDraft($quote);
            $this->assertQuoteItem($quote, $item);
            $this->rfqResponseWindowGuard->ensureQuoteRfqOpenForResponses($quote, 'remove quote lines');

            return DB::transaction(function () use ($quote, $item): Quote {
                $item->taxes()->delete();
                $before = $item->getAttributes();
                $item->delete();
                $this->auditLogger->deleted($item, $before);

                $quote->refresh();

                if ($quote->items()->count() === 0) {
                    $this->resetTotals($quote);

                    return $quote->fresh($this->defaultRelations());
                }

                [$linePayloads, $meta] = $this->buildLinePayloads($quote->fresh(['items.rfqItem', 'items.taxes']));

                return $this->persistLineChanges($quote, $linePayloads, $meta);
            });
        });
    }

    public function submitDraft(Quote $quote, int $userId): Quote
    {
        return CompanyContext::bypass(function () use ($quote, $userId): Quote {
            $this->assertDraft($quote);
            $this->rfqResponseWindowGuard->ensureQuoteRfqOpenForResponses($quote, 'submit quotes');

            if (! $quote->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' => ['Add at least one line before submitting.'],
                ]);
            }

            [$linePayloads, $meta] = $this->buildLinePayloads($quote);
            $quote = $this->persistLineChanges($quote, $linePayloads, $meta);

            $before = Arr::only($quote->getAttributes(), ['status', 'submitted_by', 'submitted_at']);

            $quote->status = 'submitted';
            $quote->submitted_by = $userId;
            $quote->submitted_at = now();
            $quote->save();

            $this->auditLogger->updated($quote, $before, $quote->only(['status', 'submitted_by', 'submitted_at']));

            return $quote->fresh($this->defaultRelations());
        });
    }

    /**
     * @param array<int, array<string, mixed>> $overrides
     * @param array<int, array<string, mixed>> $additional
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function buildLinePayloads(Quote $quote, array $overrides = [], array $additional = []): array
    {
        $quote->loadMissing('items.rfqItem', 'items.taxes');

        $linePayloads = [];
        $meta = [];

        foreach ($quote->items as $item) {
            $rfqItem = $item->rfqItem;

            if ($rfqItem === null) {
                continue;
            }

            $key = 'item_'.$item->id;
            $payload = [
                'key' => $key,
                'quantity' => (float) ($rfqItem->quantity ?? 0),
            ];

            if ($item->unit_price_minor !== null) {
                $payload['unit_price_minor'] = $item->unit_price_minor;
            } else {
                $payload['unit_price'] = (float) $item->unit_price;
            }

            $payload['tax_code_ids'] = $item->taxes->pluck('tax_code_id')->values()->all();

            if (array_key_exists($item->id, $overrides)) {
                $override = $overrides[$item->id];

                if (array_key_exists('unit_price_minor', $override) && $override['unit_price_minor'] !== null) {
                    $payload['unit_price_minor'] = (int) $override['unit_price_minor'];
                    unset($payload['unit_price']);
                } elseif (array_key_exists('unit_price', $override) && $override['unit_price'] !== null) {
                    $payload['unit_price'] = (float) $override['unit_price'];
                    unset($payload['unit_price_minor']);
                }

                if (array_key_exists('tax_code_ids', $override) && $override['tax_code_ids'] !== null) {
                    $payload['tax_code_ids'] = array_map('intval', $override['tax_code_ids']);
                }

                $meta[$key] = [
                    'type' => 'existing',
                    'model' => $item,
                    'attributes' => $override['attributes'] ?? [],
                ];
            } else {
                $meta[$key] = [
                    'type' => 'existing',
                    'model' => $item,
                    'attributes' => [],
                ];
            }

            $linePayloads[] = $payload;
        }

        foreach ($additional as $index => $row) {
            /** @var RfqItem $rfqItem */
            $rfqItem = $row['rfq_item'];
            $key = 'new_'.$rfqItem->id.'_'.$index;

            $payload = [
                'key' => $key,
                'quantity' => (float) ($rfqItem->quantity ?? 0),
                'tax_code_ids' => array_map('intval', $row['tax_code_ids'] ?? []),
            ];

            if (array_key_exists('unit_price_minor', $row) && $row['unit_price_minor'] !== null) {
                $payload['unit_price_minor'] = (int) $row['unit_price_minor'];
            } else {
                $payload['unit_price'] = (float) ($row['unit_price'] ?? 0);
            }

            $linePayloads[] = $payload;
            $meta[$key] = [
                'type' => 'new',
                'rfq_item' => $rfqItem,
                'attributes' => $row['attributes'] ?? [],
            ];
        }

        return [$linePayloads, $meta];
    }

    /**
     * @param array<int, array<string, mixed>> $linePayloads
     * @param array<string, array<string, mixed>> $meta
     */
    private function persistLineChanges(Quote $quote, array $linePayloads, array $meta): Quote
    {
        if ($linePayloads === []) {
            $this->resetTotals($quote);

            return $quote->fresh($this->defaultRelations());
        }

        $calculation = $this->totalsCalculator->calculate($quote->company_id, $quote->currency, $linePayloads);
        $minorUnit = (int) $calculation['minor_unit'];
        $lineResults = collect($calculation['lines'])->keyBy('key');

        return DB::transaction(function () use ($quote, $meta, $lineResults, $calculation, $minorUnit) {
            foreach ($meta as $key => $context) {
                $result = $lineResults->get($key);

                if ($result === null) {
                    continue;
                }

                if (($context['type'] ?? '') === 'existing') {
                    /** @var QuoteItem $item */
                    $item = $context['model'];
                    $before = $item->getOriginal();

                    $item->unit_price_minor = (int) $result['unit_price_minor'];
                    $item->unit_price = Money::fromMinor($result['unit_price_minor'], $quote->currency)->toDecimal($minorUnit);
                    $item->currency = $quote->currency;

                    $attributes = $context['attributes'] ?? [];

                    if (array_key_exists('lead_time_days', $attributes) && $attributes['lead_time_days'] !== null) {
                        $item->lead_time_days = (int) $attributes['lead_time_days'];
                    }

                    if (array_key_exists('note', $attributes)) {
                        $item->note = $attributes['note'];
                    }

                    if (array_key_exists('status', $attributes) && $attributes['status'] !== null) {
                        $item->status = (string) $attributes['status'];
                    }

                    if ($item->isDirty()) {
                        $item->save();
                        $this->auditLogger->updated($item, $before, $item->only(['unit_price', 'unit_price_minor', 'currency', 'lead_time_days', 'note', 'status']));
                    }

                    $this->lineTaxSync->sync($item, $quote->company_id, $result['taxes']);
                } else {
                    /** @var RfqItem $rfqItem */
                    $rfqItem = $context['rfq_item'];
                    $attributes = $context['attributes'] ?? [];

                    $item = QuoteItem::create([
                        'quote_id' => $quote->id,
                        'company_id' => $quote->company_id,
                        'rfq_item_id' => $rfqItem->id,
                        'unit_price' => Money::fromMinor($result['unit_price_minor'], $quote->currency)->toDecimal($minorUnit),
                        'unit_price_minor' => (int) $result['unit_price_minor'],
                        'currency' => $quote->currency,
                        'lead_time_days' => (int) ($attributes['lead_time_days'] ?? 0),
                        'note' => $attributes['note'] ?? null,
                        'status' => $attributes['status'] ?? 'pending',
                    ]);

                    $this->lineTaxSync->sync($item, $quote->company_id, $result['taxes']);
                    $this->auditLogger->created($item);
                }
            }

            $beforeTotals = Arr::only($quote->getAttributes(), ['subtotal', 'subtotal_minor', 'tax_amount', 'tax_amount_minor', 'total', 'total_minor']);

            $quote->subtotal_minor = (int) $calculation['totals']['subtotal_minor'];
            $quote->tax_amount_minor = (int) $calculation['totals']['tax_total_minor'];
            $quote->total_minor = (int) $calculation['totals']['grand_total_minor'];
            $quote->subtotal = Money::fromMinor($quote->subtotal_minor, $quote->currency)->toDecimal($minorUnit);
            $quote->tax_amount = Money::fromMinor($quote->tax_amount_minor, $quote->currency)->toDecimal($minorUnit);
            $quote->total = Money::fromMinor($quote->total_minor, $quote->currency)->toDecimal($minorUnit);
            $quote->save();

            $this->auditLogger->updated($quote, $beforeTotals, $quote->only(['subtotal', 'subtotal_minor', 'tax_amount', 'tax_amount_minor', 'total', 'total_minor']));

            return $quote->fresh($this->defaultRelations());
        });
    }

    private function resolveRfqItem(Quote $quote, int $rfqItemId): RfqItem
    {
        $rfqItem = RfqItem::query()
            ->where('rfq_id', $quote->rfq_id)
            ->where('id', $rfqItemId)
            ->first();

        if ($rfqItem === null) {
            throw ValidationException::withMessages([
                'rfq_item_id' => ['RFQ item not found for this quote.'],
            ]);
        }

        if ((float) ($rfqItem->quantity ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'rfq_item_id' => ['RFQ item must have a quantity before quoting.'],
            ]);
        }

        return $rfqItem;
    }

    private function assertDraft(Quote $quote): void
    {
        if ($quote->status !== 'draft') {
            throw ValidationException::withMessages([
                'quote' => ['Only draft quotes can be modified.'],
            ]);
        }
    }

    private function assertQuoteItem(Quote $quote, QuoteItem $item): void
    {
        if ((int) $item->quote_id !== (int) $quote->id) {
            throw ValidationException::withMessages([
                'quote_item_id' => ['Quote line does not belong to this quote.'],
            ]);
        }
    }

    private function resetTotals(Quote $quote): void
    {
        $beforeTotals = Arr::only($quote->getAttributes(), ['subtotal', 'subtotal_minor', 'tax_amount', 'tax_amount_minor', 'total', 'total_minor']);

        $quote->subtotal = '0.00';
        $quote->tax_amount = '0.00';
        $quote->total = '0.00';
        $quote->subtotal_minor = 0;
        $quote->tax_amount_minor = 0;
        $quote->total_minor = 0;
        $quote->save();

        $this->auditLogger->updated($quote, $beforeTotals, $quote->only(['subtotal', 'subtotal_minor', 'tax_amount', 'tax_amount_minor', 'total', 'total_minor']));
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return ['supplier', 'items.taxes.taxCode', 'items.rfqItem', 'documents', 'revisions.document'];
    }
}

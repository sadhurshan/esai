<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FxRateNotFoundException;
use App\Models\Currency;
use App\Models\RFQ;
use App\Support\Money\Money;
use App\Services\FxService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class RfqAwardCandidateController extends ApiController
{
    public function __construct(private readonly FxService $fxService)
    {
    }

    public function index(Request $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        Gate::forUser($user)->authorize('awardLines', $rfq);

        $rfq->loadMissing([
            'items.awards',
            'company',
            'awards.supplier',
            'awards.quote',
            'awards.rfqItem',
        ]);

        $quotes = $rfq->quotes()
            ->with([
                'supplier' => function ($query) {
                    $query->withTrashed()->with('company');
                },
                'items.award',
            ])
            ->whereNull('quotes.deleted_at')
            ->whereNull('quotes.withdrawn_at')
            ->whereIn('quotes.status', ['submitted', 'awarded'])
            ->orderByDesc('submitted_at')
            ->get();

        $companyCurrency = strtoupper($rfq->company?->currency ?? $rfq->currency ?? 'USD');
        $conversionDate = Carbon::now();

        $candidatesByLine = $this->buildCandidatesByLine($rfq->id, $quotes, $companyCurrency, $conversionDate);

        $lines = $rfq->items
            ->sortBy('line_no')
            ->values()
            ->map(function ($item) use ($candidatesByLine, $companyCurrency) {
                $lineCurrency = strtoupper($item->currency ?? $companyCurrency);
                $targetPriceMinor = $item->target_price_minor ?? $this->decimalToMinor($item->target_price, $lineCurrency);

                $candidates = $candidatesByLine[(int) $item->id] ?? [];

                $best = $this->bestCandidate($candidates);

                return [
                    'id' => (int) $item->id,
                    'line_no' => (int) $item->line_no,
                    'part_name' => $item->part_name,
                    'spec' => $item->spec,
                    'quantity' => (int) $item->quantity,
                    'uom' => $item->uom,
                    'currency' => $lineCurrency,
                    'target_price_minor' => $targetPriceMinor,
                    'candidates' => $candidates,
                    'best_price' => $best,
                ];
            })
            ->all();

        $awards = $rfq->awards
            ->where('status', 'awarded')
            ->map(function ($award) {
                return [
                    'id' => (int) $award->id,
                    'rfq_item_id' => (int) $award->rfq_item_id,
                    'supplier_id' => (int) $award->supplier_id,
                    'supplier_name' => $award->supplier?->name,
                    'quote_id' => (int) $award->quote_id,
                    'quote_item_id' => (int) $award->quote_item_id,
                    'po_id' => $award->po_id ? (int) $award->po_id : null,
                    'awarded_qty' => $award->awarded_qty !== null ? (int) $award->awarded_qty : null,
                    'awarded_at' => optional($award->awarded_at)->toIso8601String(),
                    'status' => $award->status?->value ?? (string) $award->status,
                ];
            })
            ->values()
            ->all();

        return $this->ok([
            'rfq' => [
                'id' => (int) $rfq->id,
                'number' => $rfq->number,
                'title' => $rfq->title,
                'status' => $rfq->status,
                'currency' => strtoupper($rfq->currency ?? $companyCurrency),
                'is_partially_awarded' => (bool) ($rfq->is_partially_awarded ?? false),
            ],
            'company_currency' => $companyCurrency,
            'lines' => $lines,
            'awards' => $awards,
            'meta' => [
                'quotes' => $quotes->count(),
                'suppliers' => $quotes->pluck('supplier_id')->filter()->unique()->count(),
            ],
        ]);
    }

    private function buildCandidatesByLine(int $rfqId, $quotes, string $companyCurrency, Carbon $conversionDate): array
    {
        $candidates = [];

        foreach ($quotes as $quote) {
            if (! in_array($quote->status, ['submitted', 'awarded'], true) || $quote->withdrawn_at !== null) {
                continue;
            }

            $supplier = $quote->supplier;
            $supplierName = $supplier?->name ?: $supplier?->company?->name;

            foreach ($quote->items as $item) {
                if ((int) $item->rfq_item_id === 0) {
                    continue;
                }

                $lineCurrency = strtoupper($item->currency ?? $quote->currency ?? $companyCurrency);
                $unitPriceMinor = $this->resolveMinorAmount($item->unit_price_minor, $item->unit_price, $lineCurrency);

                $converted = $this->convertToCompanyCurrency($unitPriceMinor, $lineCurrency, $companyCurrency, $conversionDate);

                $candidates[(int) $item->rfq_item_id][] = [
                    'quote_id' => (int) $quote->id,
                    'quote_item_id' => (int) $item->id,
                    'supplier_id' => $quote->supplier_id !== null ? (int) $quote->supplier_id : null,
                    'supplier_name' => $supplierName,
                    'unit_price_minor' => $unitPriceMinor,
                    'unit_price_currency' => $lineCurrency,
                    'converted_unit_price_minor' => $converted['amount_minor'],
                    'converted_currency' => $converted['currency'],
                    'conversion_unavailable' => $converted['amount_minor'] === null,
                    'lead_time_days' => $item->lead_time_days ?? $quote->lead_time_days,
                    'quote_revision' => $quote->revision_no,
                    'quote_status' => $quote->status,
                    'submitted_at' => optional($quote->submitted_at ?? $quote->created_at)->toIso8601String(),
                    'award' => $item->award ? [
                        'id' => (int) $item->award->id,
                        'status' => $item->award->status?->value ?? (string) $item->award->status,
                        'po_id' => $item->award->po_id ? (int) $item->award->po_id : null,
                        'awarded_qty' => $item->award->awarded_qty !== null ? (int) $item->award->awarded_qty : null,
                        'awarded_at' => optional($item->award->awarded_at)->toIso8601String(),
                    ] : null,
                ];
            }
        }

        return $candidates;
    }

    private function convertToCompanyCurrency(int $amountMinor, string $fromCurrency, string $companyCurrency, Carbon $asOf): array
    {
        $from = strtoupper($fromCurrency);
        $target = strtoupper($companyCurrency);

        if ($amountMinor === 0) {
            return ['amount_minor' => 0, 'currency' => $target];
        }

        if ($from === $target) {
            return ['amount_minor' => $amountMinor, 'currency' => $target];
        }

        try {
            $money = Money::fromMinor($amountMinor, $from);
            $converted = $this->fxService->convert($money, $target, $asOf);

            return ['amount_minor' => $converted->amountMinor(), 'currency' => $target];
        } catch (FxRateNotFoundException) {
            return ['amount_minor' => null, 'currency' => $target];
        }
    }

    private function bestCandidate(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $collection = collect($candidates);

        $withConversion = $collection
            ->filter(fn ($candidate) => $candidate['converted_unit_price_minor'] !== null)
            ->sortBy('converted_unit_price_minor')
            ->first();

        if ($withConversion) {
            return Arr::only($withConversion, [
                'quote_id',
                'quote_item_id',
                'supplier_id',
                'supplier_name',
                'unit_price_minor',
                'unit_price_currency',
                'converted_unit_price_minor',
                'converted_currency',
            ]);
        }

        $fallback = $collection->sortBy('unit_price_minor')->first();

        return $fallback ? Arr::only($fallback, [
            'quote_id',
            'quote_item_id',
            'supplier_id',
            'supplier_name',
            'unit_price_minor',
            'unit_price_currency',
        ]) : null;
    }

    private array $minorUnitCache = [];

    private function resolveMinorAmount(?int $minor, mixed $decimal, string $currency): int
    {
        if ($minor !== null) {
            return (int) $minor;
        }

        return $this->decimalToMinor($decimal, $currency);
    }

    private function decimalToMinor(mixed $value, string $currency): int
    {
        $amount = (float) ($value ?? 0);
        $minorUnit = $this->minorUnit($currency);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }

    private function minorUnit(string $currency): int
    {
        $key = strtoupper($currency);

        if (! array_key_exists($key, $this->minorUnitCache)) {
            $record = Currency::query()->where('code', $key)->first();
            $this->minorUnitCache[$key] = (int) ($record?->minor_unit ?? 2);
        }

        return $this->minorUnitCache[$key];
    }
}
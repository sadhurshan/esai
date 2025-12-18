<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin \App\Models\PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    private static array $minorUnitCache = [];

    public function toArray(Request $request): array
    {
        $currency = strtoupper($this->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $subtotalMinor = $this->subtotal_minor ?? $this->decimalToMinor($this->subtotal, $minorUnit);
        $taxMinor = $this->tax_amount_minor ?? $this->decimalToMinor($this->tax_amount, $minorUnit);
        $totalMinor = $this->total_minor ?? $this->decimalToMinor($this->total, $minorUnit);

        $payload = [
            'id' => $this->getKey(),
            'company_id' => $this->company_id,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'ack_status' => $this->ack_status ?? 'draft',
            'currency' => $currency,
            'incoterm' => $this->incoterm,
            'tax_percent' => $this->tax_percent,
            'subtotal' => $this->formatMinor($subtotalMinor, $minorUnit),
            'subtotal_minor' => $subtotalMinor,
            'tax_amount' => $this->formatMinor($taxMinor, $minorUnit),
            'tax_amount_minor' => $taxMinor,
            'total' => $this->formatMinor($totalMinor, $minorUnit),
            'total_minor' => $totalMinor,
            'revision_no' => $this->revision_no,
            'rfq_id' => $this->rfq_id,
            'quote_id' => $this->quote_id,
            'pdf_document_id' => $this->pdf_document_id,
            'sent_at' => optional($this->sent_at)?->toIso8601String(),
            'acknowledged_at' => optional($this->acknowledged_at)?->toIso8601String(),
            'ack_reason' => $this->ack_reason,
            'supplier' => $this->when(
                $this->relationLoaded('supplier') || $this->relationLoaded('quote'),
                function () {
                    if ($this->supplier) {
                        return [
                            'id' => $this->supplier->getKey(),
                            'name' => $this->supplier->name,
                            'email' => $this->supplier->email,
                        ];
                    }

                    if ($this->quote?->supplier) {
                        return [
                            'id' => $this->quote->supplier->getKey(),
                            'name' => $this->quote->supplier->name,
                            'email' => $this->quote->supplier->email,
                        ];
                    }

                    if ($this->quote?->supplier_id) {
                        return [
                            'id' => $this->quote->supplier_id,
                            'name' => null,
                            'email' => null,
                        ];
                    }

                    return null;
                }
            ),
            'rfq' => $this->whenLoaded('rfq', fn () => [
                'id' => $this->rfq?->getKey(),
                'number' => $this->rfq?->number,
                'title' => $this->rfq?->title,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->map(fn ($line) => (new PurchaseOrderLineResource($line))->toArray($request))
                ->values()
                ->all(), []),
            'change_orders' => $this->whenLoaded('changeOrders', fn () => $this->changeOrders
                ->map(fn ($changeOrder) => (new PoChangeOrderResource($changeOrder))->toArray($request))
                ->values()
                ->all(), []),
            'deliveries' => $this->whenLoaded('deliveries', fn () => $this->deliveries
                ->map(fn ($delivery) => (new PurchaseOrderDeliveryResource($delivery))->toArray($request))
                ->values()
                ->all(), []),
            'latest_delivery' => $this->whenLoaded('deliveries', function () use ($request) {
                $delivery = $this->deliveries->first();

                return $delivery ? (new PurchaseOrderDeliveryResource($delivery))->toArray($request) : null;
            }),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)?->toIso8601String(),
        ];

        if ($this->relationLoaded('pdfDocument') && $this->pdfDocument) {
            $downloadUrl = URL::signedRoute('purchase-orders.pdf.download', [
                'purchaseOrder' => $this->getKey(),
                'document' => $this->pdfDocument->getKey(),
            ], now()->addMinutes(30));

            $payload['pdf_document'] = [
                'id' => $this->pdfDocument->getKey(),
                'filename' => $this->pdfDocument->filename,
                'version' => $this->pdfDocument->version_number,
                'download_url' => $downloadUrl,
                'created_at' => optional($this->pdfDocument->created_at)?->toIso8601String(),
            ];
        }

        return $payload;
    }

    private function minorUnitFor(string $currency): int
    {
        if (! array_key_exists($currency, self::$minorUnitCache)) {
            $record = Currency::query()->where('code', $currency)->first();
            self::$minorUnitCache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) self::$minorUnitCache[$currency];
    }

    private function formatMinor(int $amountMinor, int $minorUnit): string
    {
        return number_format($amountMinor / (10 ** $minorUnit), $minorUnit, '.', '');
    }

    private function decimalToMinor(mixed $value, int $minorUnit): int
    {
        if ($value === null) {
            return 0;
        }

        return (int) round(((float) $value) * (10 ** $minorUnit));
    }
}

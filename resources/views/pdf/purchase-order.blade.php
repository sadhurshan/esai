<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 0;
            padding: 24px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .meta-table,
        .line-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .line-table th,
        .line-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }
        .line-table th {
            background: #f3f4f6;
            font-size: 11px;
            text-transform: uppercase;
        }
        .totals {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
        }
        .summary-table td {
            padding: 6px;
            border: 1px solid #d1d5db;
        }
        .text-right {
            text-align: right;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
@php
    $company = $purchaseOrder->company;
    $supplier = $purchaseOrder->quote?->supplier ?? $purchaseOrder->supplier;
    $currency = strtoupper($purchaseOrder->currency ?? 'USD');
    $formatMoney = static function (?int $amountMinor) use ($currency): string {
        $amountMinor ??= 0;
        $value = $amountMinor / 100;
        return $currency.' '.number_format($value, 2, '.', ',');
    };
@endphp

    <div class="header">
        <div>
            <div class="section-title">Purchase Order</div>
            <div><strong>PO Number:</strong> {{ $purchaseOrder->po_number }}</div>
            <div><strong>Status:</strong> {{ ucfirst($purchaseOrder->status) }}</div>
            <div><strong>Revision:</strong> {{ $purchaseOrder->revision_no ?? '—' }}</div>
            <div><strong>Issued:</strong> {{ optional($purchaseOrder->created_at)?->format('M d, Y') ?? '—' }}</div>
        </div>
        <div style="text-align: right;">
            <div class="section-title">Buyer</div>
            <div><strong>{{ $company?->name ?? '—' }}</strong></div>
            <div class="muted">{{ $company?->domain ?? '' }}</div>
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <div class="section-title">Bill To</div>
                <div>{{ $company?->name ?? '—' }}</div>
                <div class="muted">{{ $purchaseOrder->bill_to ?? 'Billing address on file' }}</div>
            </td>
            <td style="width: 50%;">
                <div class="section-title">Ship To</div>
                <div>{{ $company?->name ?? '—' }}</div>
                <div class="muted">{{ $purchaseOrder->ship_to ?? 'Shipping address on file' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="section-title">Supplier</div>
                @php
                    $supplierEmail = data_get($supplier, 'primary_contact_email')
                        ?? data_get($supplier, 'contact_email')
                        ?? data_get($supplier, 'email');
                @endphp
                <div>{{ $supplier?->name ?? 'Unassigned Supplier' }}</div>
                @if ($supplierEmail)
                    <div class="muted">{{ $supplierEmail }}</div>
                @endif
            </td>
            <td>
                <div class="section-title">Commercial</div>
                <div><strong>Incoterm:</strong> {{ $purchaseOrder->incoterm ?? '—' }}</div>
                <div><strong>Tax %:</strong> {{ $purchaseOrder->tax_percent !== null ? $purchaseOrder->tax_percent.'%' : '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Line Items</div>
    <table class="line-table">
        <thead>
            <tr>
                <th style="width: 6%;">Line</th>
                <th style="width: 32%;">Description</th>
                <th style="width: 12%;">Qty</th>
                <th style="width: 10%;">UOM</th>
                <th style="width: 15%;">Unit Price</th>
                <th style="width: 15%;">Tax</th>
                <th style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($purchaseOrder->lines as $line)
            @php
                $unitMinor = $line->unit_price_minor ?? (int) round(($line->unit_price ?? 0) * 100);
                $lineSubtotal = $line->line_subtotal_minor ?? ($unitMinor * $line->quantity);
                $lineTax = $line->tax_total_minor ?? 0;
                $lineTotal = $line->line_total_minor ?? ($lineSubtotal + $lineTax);
            @endphp
            <tr>
                <td>{{ $line->line_no }}</td>
                <td>{{ $line->description ?? '—' }}</td>
                <td>{{ number_format($line->quantity, 2) }}</td>
                <td>{{ $line->uom ?? 'EA' }}</td>
                <td>{{ $formatMoney($unitMinor) }}</td>
                <td>{{ $formatMoney($lineTax) }}</td>
                <td>{{ $formatMoney($lineTotal) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table class="summary-table" style="width: 40%;">
            <tr>
                <td><strong>Subtotal</strong></td>
                <td class="text-right">{{ $formatMoney($purchaseOrder->subtotal_minor ?? 0) }}</td>
            </tr>
            <tr>
                <td><strong>Tax</strong></td>
                <td class="text-right">{{ $formatMoney($purchaseOrder->tax_amount_minor ?? 0) }}</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td class="text-right">{{ $formatMoney($purchaseOrder->total_minor ?? 0) }}</td>
            </tr>
        </table>
    </div>

    <p class="muted">Generated on {{ now()->format('M d, Y H:i') }}.</p>
</body>
</html>

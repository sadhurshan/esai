<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ data_get($document ?? [], 'title', 'Document') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 0;
            padding: 32px;
            background-color: #ffffff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }
        .brand-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }
        .brand-logo {
            height: 36px;
        }
        .logo {
            max-height: 48px;
            max-width: 180px;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        h1 {
            margin: 0 0 6px 0;
            font-size: 22px;
            color: #0f172a;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background-color: #e0f2fe;
            color: #0c4a6e;
            margin-bottom: 8px;
        }
        .meta {
            font-size: 12px;
            color: #374151;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            background: #f9fafb;
        }
        .card-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 4px 0;
        }
        .row-label {
            color: #6b7280;
            margin-right: 12px;
        }
        .row-value {
            flex: 1;
            text-align: right;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .line-items {
            margin-bottom: 24px;
        }
        .totals {
            width: 260px;
            margin-left: auto;
        }
        .totals td {
            border: none;
            padding: 6px 0;
        }
        .totals td:first-child {
            color: #6b7280;
        }
        .totals td:last-child {
            text-align: right;
            color: #111827;
        }
        .note {
            margin-top: 24px;
            padding: 12px 16px;
            border-left: 3px solid #0ea5e9;
            background: #f0f9ff;
            color: #0c4a6e;
            white-space: pre-line;
        }
        .footer {
            margin-top: 32px;
            font-size: 11px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $doc = $document ?? [];
    $summary = is_array($doc['summary'] ?? null) ? $doc['summary'] : [];
    $parties = is_array($doc['parties'] ?? null) ? $doc['parties'] : [];
    $lineTable = $doc['line_table'] ?? null;
    $totals = is_array($doc['totals'] ?? null) ? $doc['totals'] : [];
    $platformLogo = asset('logo-colored-dark-text-transparent-bg.png');
@endphp

<div class="brand-bar">
    <img src="{{ $platformLogo }}" alt="{{ config('app.name', 'Elements Supply AI') }}" class="brand-logo">
</div>

<div class="header">
    <div style="flex: 1;">
        <div class="eyebrow">{{ strtoupper($doc['company_name'] ?? 'Company') }}</div>
        <h1>{{ $doc['title'] ?? 'Document' }}</h1>
        <div class="meta">
            <div><strong>{{ $doc['reference_label'] ?? 'Reference' }}:</strong> {{ $doc['reference'] ?? '—' }}</div>
            <div><strong>{{ $doc['date_label'] ?? 'Date' }}:</strong> {{ $doc['date'] ?? '—' }}</div>
        </div>
    </div>
    <div style="text-align: right; min-width: 180px;">
        @if(!empty($doc['logo_url']))
            <img src="{{ url($doc['logo_url']) }}" alt="Company Logo" class="logo">
        @endif
        @if(!empty($doc['status']))
            <div class="badge">{{ strtoupper($doc['status']) }}</div>
        @endif
    </div>
</div>

@if(!empty($summary))
    <div class="grid">
        @foreach($summary as $section)
            @php
                $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
            @endphp
            <div class="card">
                <div class="card-title">{{ $section['label'] ?? 'Summary' }}</div>
                @foreach($rows as $row)
                    <div class="row">
                        <div class="row-label">{{ $row['label'] ?? 'Field' }}</div>
                        <div class="row-value">{{ $row['value'] ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif

@if(!empty($parties))
    <div class="grid">
        @foreach($parties as $party)
            @php
                $details = is_array($party['details'] ?? null) ? $party['details'] : [];
            @endphp
            <div class="card">
                <div class="card-title">{{ $party['label'] ?? 'Party' }}</div>
                <div style="font-weight: bold; margin-bottom: 6px;">{{ $party['name'] ?? '—' }}</div>
                @foreach($details as $detail)
                    <div class="row" style="padding: 2px 0;">
                        <div class="row-label">{{ $detail['label'] ?? '' }}</div>
                        <div class="row-value">{{ $detail['value'] ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif

@if(is_array($lineTable) && !empty($lineTable['headers']))
    <div class="line-items">
        <table>
            <thead>
                <tr>
                    @foreach($lineTable['headers'] as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($lineTable['rows'] ?? [] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($lineTable['headers']) }}" style="text-align: center; color: #6b7280;">No line items available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if(!empty($totals))
    <table class="totals">
        @foreach($totals as $total)
            <tr>
                <td>{{ $total['label'] ?? 'Total' }}</td>
                <td>{{ $total['value'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($doc['footer_note']))
    <div class="note">{{ $doc['footer_note'] }}</div>
@endif

<div class="footer">
    Generated at {{ $doc['generated_at'] ?? now()->format('c') }}.
</div>
</body>
</html>

@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'Purchase order acknowledged' }}

{{ $supplierName ?? 'The supplier' }} acknowledged PO {{ $poNumber ?? 'N/A' }}. Use the details below to confirm dates or flag exceptions.

@component('mail::panel')
- **PO number:** {{ $poNumber ?? 'Not provided' }}
- **Supplier:** {{ $supplierName ?? 'Unknown supplier' }}
- **Acknowledged:** {{ $acknowledgedAt ?? 'Recently' }}
- **Status:** {{ $ackStatus ?? 'Acknowledged' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'View acknowledgement' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

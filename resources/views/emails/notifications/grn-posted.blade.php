@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'Goods receipt posted' }}

Receiving logged GRN {{ $grnNumber ?? 'N/A' }} for PO {{ $poNumber ?? 'N/A' }}. Validate quantities and trigger quality inspections if needed.

@component('mail::panel')
- **GRN number:** {{ $grnNumber ?? 'Not provided' }}
- **PO:** {{ $poNumber ?? 'N/A' }}
- **Received:** {{ $receivedDate ?? 'Recently' }}
- **Quantity logged:** {{ $receivedQuantity ?? 'Pending' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Review receipt' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

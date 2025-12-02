@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'A supplier withdrew a quote' }}

{{ $supplierName ?? 'A supplier' }} withdrew quote {{ $quoteNumber ?? 'N/A' }} for RFQ {{ $rfqNumber ?? 'N/A' }}. Capture the reason below and follow up if a replacement submission is required.

@component('mail::panel')
- **Quote number:** {{ $quoteNumber ?? 'Not provided' }}
- **Supplier:** {{ $supplierName ?? 'Unknown supplier' }}
- **Withdrawn:** {{ $withdrawnAt ?? 'Recently' }}
- **Reason provided:** {{ $withdrawReason ?? 'Not shared' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Review RFQ' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'A supplier submitted a quote' }}

{{ $supplierName ?? 'A supplier' }} just submitted pricing for RFQ {{ $rfqNumber ?? 'N/A' }}. Review the offer, compare revisions, and keep sourcing on track.

@component('mail::panel')
- **Quote number:** {{ $quoteNumber ?? 'Not provided' }}
- **Supplier:** {{ $supplierName ?? 'Unknown supplier' }}
- **Submitted:** {{ $submittedAt ?? 'Just now' }}
- **Total:** {{ $quoteTotal ?? 'Pending' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Open quote' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

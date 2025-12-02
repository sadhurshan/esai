@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'Purchase order sent' }}

PO {{ $poNumber ?? 'N/A' }} was issued to {{ $supplierName ?? 'the supplier' }}. Keep an eye on acknowledgement and downstream receiving.

@component('mail::panel')
- **PO number:** {{ $poNumber ?? 'Not provided' }}
- **Supplier:** {{ $supplierName ?? 'Unknown supplier' }}
- **Sent at:** {{ $sentAt ?? 'Just now' }}
- **PO total:** {{ $poTotal ?? 'Pending' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Open PO' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

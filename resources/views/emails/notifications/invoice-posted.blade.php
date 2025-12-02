@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'Invoice posted' }}

Invoice {{ $invoiceNumber ?? 'N/A' }} is now in the matching queue. Review totals, verify three-way match, and schedule payment before the due date.

@component('mail::panel')
- **Invoice number:** {{ $invoiceNumber ?? 'Not provided' }}
- **PO:** {{ $poNumber ?? 'N/A' }}
- **Total:** {{ $invoiceTotal ?? 'Pending' }}
- **Due date:** {{ $dueDate ?? 'TBD' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Open invoice' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

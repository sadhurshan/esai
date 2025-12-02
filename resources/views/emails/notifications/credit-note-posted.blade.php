@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'Credit note posted' }}

Finance logged credit note {{ $creditNoteNumber ?? 'N/A' }} against invoice {{ $invoiceNumber ?? 'N/A' }}. Review offsets and update vendor balance if necessary.

@component('mail::panel')
- **Credit note:** {{ $creditNoteNumber ?? 'Pending assignment' }}
- **Invoice:** {{ $invoiceNumber ?? 'N/A' }}
- **Amount:** {{ $creditAmount ?? 'Pending' }}
- **Reason:** {{ $creditReason ?? 'Not specified' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Review credit note' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

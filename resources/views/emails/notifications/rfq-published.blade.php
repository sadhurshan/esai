@component('mail::message')
@include('emails.notifications.partials.branding-header')

# {{ $notification->title ?? 'A new RFQ is live' }}

A new request for quotation from **{{ $companyName }}** is open. Review the sourcing package and confirm participation before the submission window closes.

@component('mail::panel')
- **RFQ number:** {{ $rfqNumber ?? 'Not provided' }}
- **Title:** {{ $rfqTitle ?? 'Untitled RFQ' }}
- **Owner:** {{ $ownerName ?? 'Sourcing team' }}
- **Submissions close:** {{ $submissionDeadline ?? 'TBD' }}
@endcomponent

@if(!empty($ctaUrl))
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel ?? 'Open RFQ' }}
@endcomponent
@endif

@include('emails.notifications.partials.subcopy')
@endcomponent

@php
    $audience = $audience ?? 'owner';
    $isOwner = $audience === 'owner';
    $companyName = $company->name ?? 'your company';
    $ctaUrl = $isOwner
        ? config('app.url').'/settings/company'
        : config('app.url').'/admin';
    $ctaLabel = $isOwner ? 'Review Requirements' : 'Review Decision';
@endphp

@component('mail::message')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('logo-colored-dark-text-transparent-bg.png') }}" alt="{{ config('app.name') }}" height="32">
@endcomponent
@endslot

@if($isOwner)
# Your company application needs attention

Unfortunately, **{{ $companyName }}** could not be approved at this time. Please review the feedback below and update your profile or documents before resubmitting.
@else
# Company rejected

**{{ $companyName }}** was rejected during verification. A notification has been sent to the owner with the details below.
@endif

@component('mail::panel')
**Reason provided:** {{ $reason }}
@endcomponent

@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel }}
@endcomponent

Thanks,<br>
{{ config('app.name') }} Platform Operations

@slot('subcopy')
@component('mail::subcopy')
Questions? Reply to this email or reach out through the Admin Console for follow-up.
@endcomponent
@endslot

@endcomponent

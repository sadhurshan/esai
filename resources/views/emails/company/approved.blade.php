@php
    $audience = $audience ?? 'owner';
    $isOwner = $audience === 'owner';
    $companyName = $company->name ?? 'your company';
    $ctaUrl = $isOwner
        ? config('app.url').'/dashboard'
        : config('app.url').'/admin';
    $ctaLabel = $isOwner ? 'Open Dashboard' : 'View Admin Console';
@endphp

@component('mail::message')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('logo-colored-dark-text-transparent-bg.png') }}" alt="{{ config('app.name') }}" height="32">
@endcomponent
@endslot

@if($isOwner)
# Your company is approved

Good news! **{{ $companyName }}** has been verified by the Elements Supply platform team. Supplier features, invitations, and marketplace visibility are now unlocked.
@else
# Company approved

**{{ $companyName }}** has moved out of review and is now active across the network. Keep an eye on supplier onboarding tasks or follow up if additional steps are required.
@endif

@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel }}
@endcomponent

Thanks,<br>
{{ config('app.name') }} Platform Operations

@slot('subcopy')
@component('mail::subcopy')
Need to revisit this decision? Visit the Admin Console to update the company status or reply to this email for assistance.
@endcomponent
@endslot

@endcomponent

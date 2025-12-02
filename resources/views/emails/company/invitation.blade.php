@php
    $roleLabel = \Illuminate\Support\Str::of($invitation->role ?? 'member')
        ->replace('_', ' ')
        ->title();
@endphp

@component('mail::message')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('logo-colored-dark-text-transparent-bg.png') }}" alt="{{ config('app.name') }}" height="32">
@endcomponent
@endslot

# You're invited to join {{ $invitation->company->name ?? config('app.name') }}

{{ $invitation->invitedBy?->name ?? 'A workspace admin' }} invited you to collaborate as **{{ $roleLabel }}**.

@if(! empty($invitation->message))
@component('mail::panel')
{!! nl2br(e($invitation->message)) !!}
@endcomponent
@endif

@component('mail::button', ['url' => $acceptUrl])
Accept Invitation
@endcomponent

This invitation is linked to **{{ $invitation->email }}** and
@if($invitation->expires_at)
expires {{ $invitation->expires_at->timezone(config('app.timezone'))->toDayDateTimeString() }}.
@else
will expire 48 hours after it was issued.
@endif

If you weren't expecting this email you can ignore it.

Thanks,<br>
{{ config('app.name') }}

@slot('subcopy')
@component('mail::subcopy')
Need help? Reply to this email or contact {{ config('mail.from.address') }} referencing invitation #{{ $invitation->id }}.
@endcomponent
@endslot

@endcomponent

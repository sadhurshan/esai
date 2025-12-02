@php
	$buttonUrl = $notification->meta['url'] ?? $notification->meta['href'] ?? null;
	$buttonLabel = $notification->meta['cta_label'] ?? 'View details';
@endphp

@component('mail::message')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('logo-colored-dark-text-transparent-bg.png') }}" alt="{{ config('app.name') }}" height="32">
@endcomponent
@endslot

# {{ $notification->title }}

{{ $notification->body }}

@if($buttonUrl)
@component('mail::button', ['url' => $buttonUrl])
{{ $buttonLabel }}
@endcomponent
@endif

@if(!empty($notification->meta))
@component('mail::panel')
@foreach($notification->meta as $key => $value)
@continue(in_array($key, ['url', 'href', 'cta_label']))
**{{ ucfirst(str_replace('_', ' ', $key)) }}:** {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}

@endforeach
@endcomponent
@endif

Thanks,
{{ config('app.name') }}

@slot('subcopy')
@component('mail::subcopy')
You are receiving this email because notifications for this event are enabled in your Elements Supply AI workspace.
@endcomponent
@endslot
@endcomponent

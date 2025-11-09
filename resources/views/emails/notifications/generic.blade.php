@component('mail::message')
# {{ $notification->title }}

{{ $notification->body }}

@if(!empty($notification->meta))
@component('mail::panel')
@foreach($notification->meta as $key => $value)
- **{{ ucfirst(str_replace('_', ' ', $key)) }}:** {{ is_array($value) ? json_encode($value) : $value }}
@endforeach
@endcomponent
@endif

Thanks,
{{ config('app.name') }}
@endcomponent

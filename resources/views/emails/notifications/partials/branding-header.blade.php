@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('logo-colored-dark-text-transparent-bg.png') }}" alt="{{ config('app.name') }}" height="32">
@endcomponent
@endslot

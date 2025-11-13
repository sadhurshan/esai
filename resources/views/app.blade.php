<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Elements Supply') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body class="font-sans antialiased bg-background text-foreground">
    <div id="app"></div>

    @if (!empty($initialData))
        <script>
            window.__INITIAL_DATA__ = @json($initialData);
        </script>
    @endif
</body>
</html>

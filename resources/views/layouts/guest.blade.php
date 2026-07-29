<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Account — {{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-body antialiased">
        <div class="auth-shell">
            <a href="{{ route('home') }}" class="brand-lockup brand-lockup-light mx-auto mb-8">
                <x-application-logo class="h-14 w-14" />
                <span><small>Kabacan</small><strong>PicklePlay</strong></span>
            </a>
            <div class="auth-card">{{ $slot }}</div>
        </div>
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Account — {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|sora:500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-body antialiased">
        <div class="auth-shell">
            <a href="{{ route('home') }}" class="brand-lockup brand-lockup-light mx-auto mb-8">
                <x-application-logo class="h-14 w-14" />
                <span><strong>Kabacan</strong><small>PicklePlay</small></span>
            </a>
            <div class="auth-card">{{ $slot }}</div>
        </div>
    </body>
</html>

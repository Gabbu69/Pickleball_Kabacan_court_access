<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code') · Kabacan PicklePlay</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="error-body">
        <main class="error-shell">
            <a href="{{ route('home') }}" class="brand-lockup">
                <img src="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}" alt="" class="brand-mark h-12 w-12">
                <span><small>Kabacan</small><strong>PicklePlay</strong></span>
            </a>
            <div class="error-code">@yield('code')</div>
            <p class="eyebrow">@yield('eyebrow')</p>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="error-actions">
                <a href="{{ route('home') }}" class="btn-primary">Return home</a>
                <a href="{{ route('courts.index') }}" class="btn-outline">Find courts</a>
            </div>
            <div class="error-ball" aria-hidden="true"><span></span></div>
        </main>
    </body>
</html>

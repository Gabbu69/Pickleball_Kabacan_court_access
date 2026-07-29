<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Dashboard' }} — {{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body antialiased">
        <div x-data="siteShell" class="min-h-screen">
            <button
                type="button"
                class="motion-toggle"
                @click="toggleMotion()"
                :aria-pressed="motionPaused.toString()"
                :title="motionPaused ? 'Resume animations' : 'Pause animations'"
            >
                <span aria-hidden="true" x-text="motionPaused ? '▶' : 'Ⅱ'"></span>
                <span x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></span>
            </button>
            @include('layouts.navigation')

            <div class="dashboard-content">
                @isset($header)
                    <header class="dashboard-header">
                        <div class="dashboard-container py-7">{{ $header }}</div>
                    </header>
                @endisset

                <main class="dashboard-container py-8">
                    @if (session('success') || session('status'))
                        <div class="flash flash-success mb-6" role="status">{{ session('success') ?? session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="flash flash-error mb-6" role="alert">{{ $errors->first() }}</div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

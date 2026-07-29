<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Dashboard' }} — {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|sora:500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body antialiased">
        <div class="min-h-screen">
            @include('layouts.navigation')

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
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ trim($__env->yieldContent('title', 'Kabacan PicklePlay — Find your next court')) }}</title>
        <meta name="description" content="{{ trim($__env->yieldContent('description', 'Discover verified pickleball courts in Kabacan, check schedules, and manage reservations directly with local court owners.')) }}">
        <meta property="og:title" content="{{ trim($__env->yieldContent('title', 'Kabacan PicklePlay')) }}">
        <meta property="og:description" content="{{ trim($__env->yieldContent('description', 'Verified Kabacan courts, schedules, reservations, payments, and community updates.')) }}">
        <meta property="og:image" content="{{ asset('images/kabacan-pickleplay-mark.svg') }}?v=2">
        <meta property="og:type" content="website">
        <meta name="theme-color" content="#081a27">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/kabacan-pickleplay-mark.svg') }}?v=2">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|sora:500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="public-body antialiased">
        <div x-data="siteShell" class="min-h-screen">
            <header class="site-header">
                <div class="site-container flex min-h-[4.8rem] items-center justify-between gap-5">
                    <a href="{{ route('home') }}" class="brand-lockup" aria-label="Kabacan PicklePlay home">
                        <img src="{{ asset('images/kabacan-pickleplay-mark.svg') }}?v=2" alt="" class="brand-mark h-11 w-11">
                        <span>
                            <small>Kabacan</small>
                            <strong>PicklePlay</strong>
                        </span>
                    </a>

                    <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary navigation">
                        <a class="nav-pill {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">Home</a>
                        <a class="nav-pill {{ request()->routeIs('courts.*') ? 'is-active' : '' }}" href="{{ route('courts.index') }}">Find courts</a>
                        <a class="nav-pill {{ request()->routeIs('content.*') ? 'is-active' : '' }}" href="{{ route('content.index') }}">Community</a>
                    </nav>

                    <div class="hidden items-center gap-2 lg:flex">
                        @auth
                            <a class="btn-quiet" href="{{ route('bookings.index') }}">My bookings</a>
                            <a class="btn-primary" href="{{ route('dashboard') }}">Dashboard</a>
                        @else
                            <a class="btn-quiet" href="{{ route('login') }}">Log in</a>
                            <a class="btn-primary" href="{{ route('register') }}">Join & play</a>
                        @endauth
                    </div>

                    <button type="button" class="menu-button lg:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-menu">
                        <span class="sr-only">Toggle menu</span>
                        <span :class="{ 'rotate-45 translate-y-[7px]': open }"></span>
                        <span :class="{ 'opacity-0': open }"></span>
                        <span :class="{ '-rotate-45 -translate-y-[7px]': open }"></span>
                    </button>
                </div>

                <div id="mobile-menu" x-cloak x-show="open" x-transition.opacity class="mobile-menu lg:hidden">
                    <div class="site-container grid gap-2 py-4">
                        <a href="{{ route('home') }}">Home</a>
                        <a href="{{ route('courts.index') }}">Find courts</a>
                        <a href="{{ route('content.index') }}">Community updates</a>
                        @auth
                            <a href="{{ route('bookings.index') }}">My bookings</a>
                            <a class="mobile-menu-primary" href="{{ route('dashboard') }}">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}">Log in</a>
                            <a class="mobile-menu-primary" href="{{ route('register') }}">Join & play</a>
                        @endauth
                    </div>
                </div>
            </header>

            @if (session('success') || session('status'))
                <div class="site-container pt-4">
                    <div class="flash flash-success" role="status">{{ session('success') ?? session('status') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="site-container pt-4">
                    <div class="flash flash-error" role="alert">{{ $errors->first() }}</div>
                </div>
            @endif

            <main>
                @yield('content')
            </main>

            <footer class="site-footer">
                <div class="site-container grid gap-10 py-14 md:grid-cols-[1.3fr_0.7fr_0.7fr]">
                    <div>
                        <a href="{{ route('home') }}" class="brand-lockup brand-lockup-light">
                            <img src="{{ asset('images/kabacan-pickleplay-mark.svg') }}?v=2" alt="" class="brand-mark h-12 w-12">
                            <span><small>Kabacan</small><strong>PicklePlay</strong></span>
                        </a>
                        <p class="mt-5 max-w-xl text-sm leading-7 text-white/65">A homegrown court-discovery and reservation platform built for verified pickleball venues in Kabacan, Cotabato.</p>
                    </div>
                    <div>
                        <p class="footer-label">Explore</p>
                        <div class="footer-links">
                            <a href="{{ route('courts.index') }}">Court directory</a>
                            <a href="{{ route('content.index', ['type' => 'tournament']) }}">Tournaments</a>
                            <a href="{{ route('content.index', ['type' => 'maintenance']) }}">Maintenance notices</a>
                        </div>
                    </div>
                    <div>
                        <p class="footer-label">Trust</p>
                        <div class="footer-links">
                            <a href="{{ route('content.index') }}#verification">Verification policy</a>
                            <a href="{{ route('content.index') }}#privacy">Privacy</a>
                            <a href="{{ route('content.index') }}#terms">Terms</a>
                        </div>
                    </div>
                </div>
                <div class="border-t border-white/10">
                    <div class="site-container flex flex-col gap-2 py-5 text-xs text-white/45 sm:flex-row sm:items-center sm:justify-between">
                        <p>© {{ date('Y') }} Kabacan PicklePlay.</p>
                        <p>Verified local facts. No invented court information.</p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>

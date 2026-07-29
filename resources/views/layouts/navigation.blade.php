@php
    $user = auth()->user();
    $role = $user->role->value;
    $links = [
        ['label' => 'My bookings', 'route' => 'bookings.index', 'show' => true],
        ['label' => 'Profile', 'route' => 'profile.edit', 'show' => true],
        ['label' => 'Owner dashboard', 'route' => 'owner.dashboard', 'show' => in_array($role, ['owner', 'admin'])],
        ['label' => 'Manage courts', 'route' => 'owner.courts.index', 'show' => in_array($role, ['owner', 'admin'])],
        ['label' => 'Reservations', 'route' => 'owner.bookings.index', 'show' => in_array($role, ['owner', 'admin'])],
        ['label' => 'Reports', 'route' => 'owner.reports.index', 'show' => in_array($role, ['owner', 'admin'])],
        ['label' => 'Admin', 'route' => 'admin.dashboard', 'show' => $role === 'admin'],
        ['label' => 'Verification', 'route' => 'admin.courts.index', 'show' => $role === 'admin'],
        ['label' => 'Owner applications', 'route' => 'admin.owner-applications.index', 'show' => $role === 'admin'],
        ['label' => 'Users', 'route' => 'admin.users.index', 'show' => $role === 'admin'],
        ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'show' => $role === 'admin'],
        ['label' => 'Content', 'route' => 'admin.content.index', 'show' => $role === 'admin'],
    ];
@endphp

<nav x-data="{ open: false }" class="dashboard-nav">
    <div class="dashboard-container flex min-h-[4.6rem] items-center justify-between gap-5">
        <a href="{{ route('dashboard') }}" class="brand-lockup">
            <x-application-logo class="h-10 w-10" />
            <span><strong>Kabacan</strong><small>PicklePlay</small></span>
        </a>

        <div class="hidden min-w-0 items-center gap-1 xl:flex">
            @foreach ($links as $link)
                @if ($link['show'])
                    <a class="dashboard-link {{ request()->routeIs($link['route']) || request()->routeIs(Str::beforeLast($link['route'], '.').'.*') ? 'is-active' : '' }}" href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </div>

        <div class="hidden items-center gap-2 xl:flex">
            @if ($user->unreadNotifications->count())
                <span class="notification-count" title="Unread notifications">{{ $user->unreadNotifications->count() }}</span>
            @endif
            <a class="btn-quiet" href="{{ route('home') }}">View site</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-dark">Log out</button>
            </form>
        </div>

        <button type="button" class="menu-button xl:hidden" @click="open = !open" aria-label="Toggle dashboard menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div x-cloak x-show="open" class="dashboard-mobile-menu xl:hidden">
        <div class="dashboard-container grid gap-1 py-4">
            @foreach ($links as $link)
                @if ($link['show'])
                    <a href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
            <a href="{{ route('home') }}">View public website</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full text-left">Log out</button>
            </form>
        </div>
    </div>
</nav>

@php
    $user = auth()->user();
    $role = $user->role->value;
    $groups = [
        [
            'label' => 'My play',
            'show' => true,
            'links' => [
                ['label' => 'My bookings', 'route' => 'bookings.index', 'icon' => 'calendar'],
                ['label' => 'Profile', 'route' => 'profile.edit', 'icon' => 'user'],
            ],
        ],
        [
            'label' => 'Court operations',
            'show' => in_array($role, ['owner', 'admin']),
            'links' => [
                ['label' => 'Owner overview', 'route' => 'owner.dashboard', 'icon' => 'grid'],
                ['label' => 'Reservations', 'route' => 'owner.bookings.index', 'icon' => 'ticket'],
                ['label' => 'QR check-in', 'route' => 'owner.check-ins.index', 'icon' => 'scan'],
                ['label' => 'Manage courts', 'route' => 'owner.courts.index', 'icon' => 'court'],
                ['label' => 'Reports', 'route' => 'owner.reports.index', 'icon' => 'chart'],
            ],
        ],
        [
            'label' => 'Administration',
            'show' => $role === 'admin',
            'links' => [
                ['label' => 'Control room', 'route' => 'admin.dashboard', 'icon' => 'shield'],
                ['label' => 'Court verification', 'route' => 'admin.courts.index', 'icon' => 'check'],
                ['label' => 'Owner applications', 'route' => 'admin.owner-applications.index', 'icon' => 'briefcase'],
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users'],
                ['label' => 'Review moderation', 'route' => 'admin.reviews.index', 'icon' => 'star'],
                ['label' => 'Content', 'route' => 'admin.content.index', 'icon' => 'megaphone'],
            ],
        ],
    ];
@endphp

<div x-data="{ navOpen: false }">
    <aside class="dashboard-sidebar" aria-label="Dashboard navigation">
        <a href="{{ route('dashboard') }}" class="brand-lockup dashboard-brand">
            <x-application-logo class="h-11 w-11" />
            <span><small>Kabacan</small><strong>PicklePlay</strong></span>
        </a>

        <nav class="dashboard-sidebar-nav">
            @foreach ($groups as $group)
                @if ($group['show'])
                    <section>
                        <p>{{ $group['label'] }}</p>
                        @foreach ($group['links'] as $link)
                            @php
                                $prefix = Str::beforeLast($link['route'], '.');
                                $active = request()->routeIs($link['route']) || request()->routeIs($prefix.'.*');
                            @endphp
                            <a class="{{ $active ? 'is-active' : '' }}" href="{{ route($link['route']) }}">
                                <span class="sidebar-icon sidebar-icon-{{ $link['icon'] }}" aria-hidden="true"></span>
                                <strong>{{ $link['label'] }}</strong>
                                @if ($link['route'] === 'bookings.index' && $user->unreadNotifications->count())
                                    <i>{{ min(99, $user->unreadNotifications->count()) }}</i>
                                @endif
                            </a>
                        @endforeach
                    </section>
                @endif
            @endforeach
        </nav>

        <div class="dashboard-sidebar-footer">
            <div><span>{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span><p><strong>{{ $user->name }}</strong><small>{{ ucfirst($role) }}</small></p></div>
            <x-theme-toggle class="dashboard-theme-toggle" />
            <a href="{{ route('home') }}">View public site</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button>Log out</button>
            </form>
        </div>
    </aside>

    <header class="dashboard-mobile-bar">
        <a href="{{ route('dashboard') }}" class="brand-lockup">
            <x-application-logo class="h-10 w-10" />
            <span><small>Kabacan</small><strong>PicklePlay</strong></span>
        </a>
        <div class="flex items-center gap-2">
            <x-theme-toggle class="theme-toggle-compact" />
            <button type="button" class="menu-button" @click="navOpen = !navOpen" :aria-expanded="navOpen.toString()" aria-controls="dashboard-mobile-menu">
                <span class="sr-only">Toggle dashboard navigation</span>
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <div id="dashboard-mobile-menu" x-cloak x-show="navOpen" x-transition.opacity class="dashboard-mobile-menu">
        <div class="mobile-menu-theme">
            <span>Appearance</span>
            <x-theme-toggle />
        </div>
        @foreach ($groups as $group)
            @if ($group['show'])
                <section>
                    <p>{{ $group['label'] }}</p>
                    @foreach ($group['links'] as $link)
                        <a href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
                    @endforeach
                </section>
            @endif
        @endforeach
        <a href="{{ route('home') }}">View public website</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button>Log out</button>
        </form>
    </div>
</div>

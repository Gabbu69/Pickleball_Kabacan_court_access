<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="eyebrow">Court operations</p><h1 class="dashboard-title">Owner dashboard</h1></div>
            <a class="btn-primary" href="{{ route('owner.courts.create') }}">Add a court listing</a>
        </div>
    </x-slot>

    <div class="metric-grid">
        <div class="metric-card"><span>Pending requests</span><strong>{{ $pendingBookings }}</strong><small>Need a reservation decision</small></div>
        <div class="metric-card"><span>Today’s bookings</span><strong>{{ $todayBookings }}</strong><small>Across assigned courts</small></div>
        <div class="metric-card metric-card-dark"><span>Net revenue</span><strong>₱{{ number_format($netRevenue / 100, 2) }}</strong><small>₱{{ number_format($grossRevenue / 100, 2) }} gross · ₱{{ number_format($refunds / 100, 2) }} refunded</small></div>
        <div class="metric-card"><span>Managed courts</span><strong>{{ $courts->count() }}</strong><small>Draft, review, or published</small></div>
    </div>

    <div class="dashboard-grid mt-7">
        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Reservation desk</p><h2>Latest booking requests</h2></div><a href="{{ route('owner.bookings.index') }}">View all ↗</a></div>
            <div class="booking-list">
                @forelse ($latestBookings as $booking)
                    <a class="booking-row" href="{{ route('owner.bookings.index', ['court' => $booking->court_id]) }}">
                        <div class="booking-date-block"><span>{{ $booking->starts_at->format('M') }}</span><strong>{{ $booking->starts_at->format('d') }}</strong></div>
                        <div class="min-w-0 flex-1"><h3>{{ $booking->user->name }}</h3><p>{{ $booking->court->name }} · {{ $booking->courtUnit->name }}</p><small>{{ $booking->starts_at->format('g:i A') }} · {{ $booking->reference }}</small></div>
                        <span class="status status-{{ $booking->status->value }}">{{ str_replace('_', ' ', ucfirst($booking->status->value)) }}</span>
                    </a>
                @empty
                    <div class="panel-empty"><h3>No booking activity yet.</h3><p>Published court schedules will create the reservation queue.</p></div>
                @endforelse
            </div>
        </section>

        <aside class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Court readiness</p><h2>Your listings</h2></div></div>
            <div class="court-readiness-list">
                @forelse ($courts as $court)
                    <a href="{{ route('owner.courts.manage', $court) }}">
                        <div><strong>{{ $court->name }}</strong><p>{{ $court->units_count }} playable courts · {{ $court->bookings_count }} bookings</p></div>
                        <span class="status status-{{ $court->status->value }}">{{ str_replace('_', ' ', ucfirst($court->status->value)) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Create a listing and submit evidence to begin verification.</p>
                @endforelse
            </div>
        </aside>
    </div>
</x-app-layout>

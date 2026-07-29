<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Player dashboard</p>
                <h1 class="dashboard-title">Your court time, all in one place.</h1>
            </div>
            <a href="{{ route('courts.index') }}" class="btn-primary">Find another court</a>
        </div>
    </x-slot>

    <div class="dashboard-grid">
        <section class="dashboard-main">
            <div class="panel">
                <div class="panel-heading">
                    <div><p class="eyebrow">Reservations</p><h2>Booking history</h2></div>
                    <span class="count-badge">{{ $bookings->total() }}</span>
                </div>
                <div class="booking-list">
                    @forelse ($bookings as $booking)
                        <a href="{{ route('bookings.show', $booking) }}" class="booking-row">
                            <div class="booking-date-block">
                                <span>{{ $booking->starts_at->format('M') }}</span>
                                <strong>{{ $booking->starts_at->format('d') }}</strong>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3>{{ $booking->court->name }}</h3>
                                    <span class="status status-{{ $booking->status->value }}">{{ str_replace('_', ' ', ucfirst($booking->status->value)) }}</span>
                                </div>
                                <p>{{ $booking->courtUnit->name }} · {{ $booking->starts_at->format('g:i A') }}–{{ $booking->ends_at->format('g:i A') }}</p>
                                <small>{{ $booking->reference }} · {{ $booking->formatted_price }} · Payment {{ $booking->payment_status->value }}</small>
                            </div>
                            <span class="round-arrow">↗</span>
                        </a>
                    @empty
                        <div class="panel-empty">
                            <img src="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}" alt="">
                            <h3>No reservations yet.</h3>
                            <p>Browse verified courts and choose a schedule to start.</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">{{ $bookings->links() }}</div>
            </div>

            @if ($favorites->isNotEmpty())
                <div class="panel mt-6">
                    <div class="panel-heading"><div><p class="eyebrow">Saved courts</p><h2>Your favorites</h2></div></div>
                    <div class="favorite-grid">
                        @foreach ($favorites as $court)
                            <a href="{{ route('courts.show', $court) }}">
                                @if ($court->primaryPhoto)<img src="{{ $court->primaryPhoto->optimizedUrl(320) }}" alt="{{ $court->primaryPhoto->alt_text }}" loading="lazy" decoding="async">@endif
                                <span><strong>{{ $court->name }}</strong><small>{{ $court->barangay ?: 'Kabacan' }}</small></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="panel mt-6">
                <div class="panel-heading"><div><p class="eyebrow">Court owners</p><h2>Manage a Kabacan venue</h2></div></div>
                @if ($ownerApplication)
                    <div class="application-status">
                        <span class="status status-{{ $ownerApplication->status }}">{{ ucfirst($ownerApplication->status) }}</span>
                        <div><strong>{{ $ownerApplication->proposed_court_name }}</strong><p>{{ $ownerApplication->reviewer_notes ?: 'Your evidence is waiting for administrator review.' }}</p></div>
                    </div>
                @elseif (! auth()->user()->isOwner())
                    <form method="POST" action="{{ route('owner-applications.store') }}" enctype="multipart/form-data" class="form-grid mt-5">
                        @csrf
                        <div class="sm:col-span-2">
                            <label for="owner-proposed-court">Court name</label>
                            <input id="owner-proposed-court" class="form-input" name="proposed_court_name" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="owner-application-message">Tell us how you are connected to the venue</label>
                            <textarea id="owner-application-message" class="form-input min-h-28" name="message" required minlength="30"></textarea>
                        </div>
                        <div>
                            <label for="owner-application-evidence">Ownership or management evidence</label>
                            <input id="owner-application-evidence" class="form-input" type="file" name="evidence" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        </div>
                        <div class="flex items-end"><button class="btn-primary">Submit application</button></div>
                    </form>
                @else
                    <a href="{{ route('owner.dashboard') }}" class="btn-outline">Open owner dashboard</a>
                @endif
            </div>
        </section>

        <aside class="dashboard-side">
            <div class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Notifications</p><h2>Latest updates</h2></div></div>
                <div class="notification-list">
                    @forelse ($notifications as $notification)
                        <a href="{{ route('notifications.read', $notification->id) }}" class="{{ $notification->read_at ? '' : 'is-unread' }}">
                            <span></span>
                            <div><strong>{{ $notification->data['title'] ?? 'Update' }}</strong><p>{{ $notification->data['message'] ?? '' }}</p><small>{{ $notification->created_at->diffForHumans() }}</small></div>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">No notifications yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="panel mt-6">
                <div class="panel-heading"><div><p class="eyebrow">Waitlist</p><h2>Slots you’re watching</h2></div></div>
                <div class="waitlist-list">
                    @forelse ($waitlist as $entry)
                        <div>
                            <strong>{{ $entry->court->name }}</strong>
                            <p>{{ $entry->starts_at->format('M j, Y g:i A') }}</p>
                            <span class="status status-{{ $entry->status }}">{{ ucfirst($entry->status) }}</span>
                            @if ($entry->latestOffer?->isActive())
                                <p class="offer-countdown">Priority expires {{ $entry->latestOffer->expires_at->diffForHumans() }}</p>
                                <form method="POST" action="{{ route('waitlist-offers.accept', $entry->latestOffer) }}">
                                    @csrf
                                    <button class="btn-primary w-full justify-center">Claim this slot</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Unavailable slots can be added to your waitlist from a court page.</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>

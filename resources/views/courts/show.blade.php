@extends('layouts.public')

@section('title', $court->name.' — Kabacan PicklePlay')
@section('description', $court->short_description ?: 'View verified court information, operating hours, location, rates, and availability in Kabacan.')

@section('content')
    <section class="court-detail-hero">
        <div class="site-container py-8">
            <a href="{{ route('courts.index') }}" class="back-link">← Back to all courts</a>
            <div class="court-gallery mt-6">
                @forelse ($court->photos->take(5) as $photo)
                    <figure class="{{ $loop->first ? 'court-gallery-main' : '' }}">
                        <img src="{{ asset($photo->path) }}" alt="{{ $photo->alt_text }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        @if ($photo->caption)<figcaption>{{ $photo->caption }}</figcaption>@endif
                    </figure>
                @empty
                    <div class="court-gallery-empty"><img src="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}" alt=""><span>Verified photo pending</span></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="court-detail-main">
        <div class="site-container grid gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="verified-chip verified-chip-static"><span></span> Verified court</span>
                    <span class="detail-chip">{{ ucfirst($court->environment) }}</span>
                    <span class="detail-chip">{{ ucfirst($court->venue_type) }}</span>
                </div>
                <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="eyebrow">{{ $court->barangay ?: 'Kabacan' }}, Cotabato</p>
                        <h1 class="detail-title">{{ $court->name }}</h1>
                        <p class="mt-4 max-w-3xl text-base leading-8 text-slate-600">{{ $court->description ?: $court->short_description }}</p>
                    </div>
                    @auth
                        @php $isFavorite = auth()->user()->favoriteCourts()->whereKey($court->id)->exists(); @endphp
                        <form method="POST" action="{{ $isFavorite ? route('favorites.destroy', $court) : route('favorites.store', $court) }}">
                            @csrf
                            @if ($isFavorite) @method('DELETE') @endif
                            <button class="favorite-button {{ $isFavorite ? 'is-favorite' : '' }}">{{ $isFavorite ? '♥ Saved' : '♡ Save' }}</button>
                        </form>
                    @endauth
                </div>

                <div class="detail-facts">
                    <div><span>Address</span><strong>{{ $court->full_address }}</strong></div>
                    <div><span>Contact</span><strong>{{ $court->phone ?: ($court->email ?: 'Via venue page') }}</strong></div>
                    <div><span>Rating</span><strong>{{ $court->published_reviews_avg_rating ? number_format($court->published_reviews_avg_rating, 1).' / 5' : 'No completed-booking reviews yet' }}</strong></div>
                    <div><span>Cancellation</span><strong>Up to {{ $court->cancellation_cutoff_hours }} hours before play</strong></div>
                </div>

                <section class="detail-section">
                    <p class="eyebrow">Amenities</p>
                    <h2>What the venue confirms.</h2>
                    <div class="amenity-grid">
                        @forelse ($court->amenities as $amenity)
                            <div><span aria-hidden="true">✓</span>{{ $amenity->name }}</div>
                        @empty
                            <p class="text-slate-500">No amenities have been verified for this court.</p>
                        @endforelse
                    </div>
                </section>

                <section class="detail-section">
                    <p class="eyebrow">Operating hours</p>
                    <h2>Plan your visit.</h2>
                    <div class="hours-grid">
                        @php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
                        @foreach ($days as $index => $day)
                            @php $hours = $court->operatingHours->firstWhere('day_of_week', $index); @endphp
                            <div>
                                <span>{{ $day }}</span>
                                <strong>
                                    @if (! $hours || $hours->is_closed)
                                        Closed
                                    @else
                                        {{ \Carbon\Carbon::parse($hours->opens_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($hours->closes_at)->format('g:i A') }}
                                    @endif
                                </strong>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="detail-section">
                    <p class="eyebrow">Verified player reviews</p>
                    <h2>Feedback tied to completed play.</h2>
                    <div class="review-list">
                        @forelse ($court->publishedReviews as $review)
                            <article>
                                <div class="flex items-center justify-between gap-3">
                                    <strong>{{ $review->user->name }}</strong>
                                    <span class="rating-chip">★ {{ $review->rating }}</span>
                                </div>
                                <p>{{ $review->body }}</p>
                                <small>Verified booking · {{ $review->created_at->format('M Y') }}</small>
                            </article>
                        @empty
                            <div class="review-empty">Be the first player to review this court after a completed booking.</div>
                        @endforelse
                    </div>
                </section>

                <section class="detail-section">
                    <p class="eyebrow">Location</p>
                    <h2>Find the verified pin.</h2>
                    <div class="single-map" data-single-map data-lat="{{ $court->latitude }}" data-lng="{{ $court->longitude }}" data-name="{{ $court->name }}"></div>
                    @if ($court->google_maps_url)
                        <a class="btn-outline mt-4" href="{{ $court->google_maps_url }}" target="_blank" rel="noopener">Open verified directions ↗</a>
                    @endif
                </section>

                <section class="verification-note">
                    <strong>Why this listing is marked verified</strong>
                    <p>Kabacan PicklePlay publishes a court only after required venue details and evidence have been reviewed.</p>
                    @foreach ($court->verifications as $verification)
                        <div>
                            <span>{{ str_replace('_', ' ', ucfirst($verification->type)) }}</span>
                            @if ($verification->source_url)
                                <a href="{{ $verification->source_url }}" target="_blank" rel="noopener">View source ↗</a>
                            @endif
                        </div>
                    @endforeach
                </section>
            </div>

            <aside>
                <div class="booking-panel" x-data="availabilityPicker('{{ $court->slug }}', '{{ now()->format('Y-m-d') }}')">
                    <div class="booking-panel-head">
                        <p class="eyebrow">Reserve this court</p>
                        <h2>Pick a date and slot.</h2>
                        <p>All times use Philippine Standard Time.</p>
                    </div>
                    <label class="booking-label" for="booking-date">Date</label>
                    <input id="booking-date" type="date" x-model="date" @change="load()" min="{{ now()->format('Y-m-d') }}" max="{{ now()->addDays(60)->format('Y-m-d') }}" class="form-input">
                    <div class="availability-status" x-show="loading"><span></span> Checking live schedule…</div>
                    <p class="availability-error" x-show="error" x-text="error"></p>
                    <div class="slot-list" x-show="!loading">
                        <template x-for="slot in slots" :key="`${slot.unit_id}-${slot.start_at}`">
                            <button
                                type="button"
                                class="slot-button"
                                :disabled="!['available', 'booked'].includes(slot.status)"
                                :class="{
                                    'is-selected': selected?.start_at === slot.start_at && selected?.unit_id === slot.unit_id,
                                    'is-waitlisted': waitlistSelection?.start_at === slot.start_at && waitlistSelection?.unit_id === slot.unit_id,
                                    'is-unavailable': slot.status !== 'available'
                                }"
                                @click="slot.status === 'available'
                                    ? (selected = slot, waitlistSelection = null)
                                    : slot.status === 'booked' && (waitlistSelection = slot, selected = null)"
                            >
                                <span><strong x-text="slot.label"></strong><small x-text="slot.unit_name"></small></span>
                                <b x-text="slot.status === 'available' ? slot.price_label : slot.status"></b>
                            </button>
                        </template>
                        <p class="slot-empty" x-show="loaded && slots.length === 0">No configured slots for this date.</p>
                    </div>

                    @auth
                        <form method="POST" action="{{ route('bookings.store', $court) }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="court_unit_id" :value="selected?.unit_id || ''">
                            <input type="hidden" name="date" :value="date">
                            <input type="hidden" name="start_time" :value="selected?.start_time || ''">
                            <label class="booking-label" for="player_notes">Note to the owner <span>(optional)</span></label>
                            <textarea id="player_notes" name="player_notes" class="form-input min-h-24" placeholder="Equipment needs or helpful details"></textarea>
                            <button class="btn-primary mt-4 w-full justify-center" :disabled="!selected">Request reservation</button>
                        </form>

                        <form method="POST" action="{{ route('waitlist.store', $court) }}" class="waitlist-form" x-show="waitlistSelection" x-cloak>
                            @csrf
                            <input type="hidden" name="court_unit_id" :value="waitlistSelection?.unit_id || ''">
                            <input type="hidden" name="date" :value="date">
                            <input type="hidden" name="start_time" :value="waitlistSelection?.start_time || ''">
                            <p>That time is unavailable. Join its waitlist and we will notify you if it opens.</p>
                            <button class="btn-outline w-full justify-center">Join this waitlist</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary mt-5 w-full justify-center">Log in to reserve</a>
                    @endauth

                    <div class="payment-policy">
                        <span>Payment policy</span>
                        <strong>{{ match($court->payment_policy) {'pay_on_site' => 'Pay at venue', 'proof_required' => 'Verified prepayment required', default => 'Prepay or pay at venue'} }}</strong>
                    </div>
                </div>
            </aside>
        </div>
    </section>
@endsection

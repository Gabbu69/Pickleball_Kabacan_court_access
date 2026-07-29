@props(['court'])

<article class="court-card reveal" data-court-card>
    <a href="{{ route('courts.show', $court) }}" class="court-card-media">
        @if ($court->primaryPhoto)
            <img src="{{ asset($court->primaryPhoto->path) }}" alt="{{ $court->primaryPhoto->alt_text }}" loading="lazy">
        @else
            <div class="court-card-placeholder" aria-hidden="true">
                <span class="mini-court-lines"></span>
                <img src="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}" alt="">
            </div>
        @endif
        <span class="verified-chip"><span></span> Verified</span>
        <span class="environment-chip">{{ ucfirst($court->environment) }}</span>
    </a>
    <div class="court-card-body">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="eyebrow">{{ $court->barangay ?: 'Kabacan' }}</p>
                <h3><a href="{{ route('courts.show', $court) }}">{{ $court->name }}</a></h3>
            </div>
            @if ($court->published_reviews_avg_rating)
                <span class="rating-chip">★ {{ number_format($court->published_reviews_avg_rating, 1) }}</span>
            @endif
        </div>
        <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-500">{{ $court->short_description ?: $court->full_address }}</p>
        @if ($court->amenities->isNotEmpty())
            <div class="amenity-row">
                @foreach ($court->amenities->take(3) as $amenity)
                    <span>{{ $amenity->name }}</span>
                @endforeach
                @if ($court->amenities->count() > 3)
                    <span>+{{ $court->amenities->count() - 3 }}</span>
                @endif
            </div>
        @endif
        <div class="court-card-footer">
            <div>
                <small>From</small>
                <strong>
                    @if ($court->starting_price_centavos !== null)
                        ₱{{ number_format($court->starting_price_centavos / 100, 2) }}
                    @else
                        Schedule pending
                    @endif
                </strong>
            </div>
            <a href="{{ route('courts.show', $court) }}" class="round-arrow" aria-label="View {{ $court->name }}">↗</a>
        </div>
    </div>
</article>

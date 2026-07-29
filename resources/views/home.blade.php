@extends('layouts.public')

@section('title', 'Kabacan PicklePlay — Verified local courts and reservations')
@section('description', 'Find verified pickleball courts in Kabacan, view live local schedules, and manage reservations directly.')

@section('content')
    <section class="hero-section" data-hero-section>
        <div class="hero-grid" aria-hidden="true"></div>
        <div class="site-container hero-layout">
            <div class="hero-copy reveal">
                <p class="eyebrow eyebrow-light">Built in Kabacan. Built for the rally.</p>
                <h1>Find the court.<br><span>Own the play.</span></h1>
                <p class="hero-lead">Discover verified pickleball courts, see real schedules, reserve a slot, and keep every booking in one local platform.</p>
                <form action="{{ route('courts.index') }}" method="GET" class="hero-search">
                    <label class="sr-only" for="hero-search">Search courts or barangays</label>
                    <span aria-hidden="true">⌖</span>
                    <input id="hero-search" name="q" placeholder="Court name or barangay" autocomplete="off">
                    <button>Find a court</button>
                </form>
                <div class="hero-trust">
                    <span><i></i> Verified facts only</span>
                    <span><i></i> Direct local reservations</span>
                    <span><i></i> No third-party booking handoff</span>
                </div>
            </div>

            <div class="hero-motion reveal" data-hero-motion aria-label="Animated Kabacan PicklePlay court preview">
                <div class="motion-card motion-card-main">
                    <div class="motion-card-top">
                        <span>Kabacan court finder</span>
                        <small>Live local schedules</small>
                    </div>
                    <div class="motion-court">
                        <span class="motion-net"></span>
                        <span class="motion-kitchen motion-kitchen-a"></span>
                        <span class="motion-kitchen motion-kitchen-b"></span>
                    </div>
                    <div class="motion-card-bottom">
                        <span>{{ $courtCount }} verified {{ Str::plural('venue', $courtCount) }}</span>
                        <span>{{ $barangayCount }} {{ Str::plural('barangay', $barangayCount) }}</span>
                    </div>
                </div>
                <div class="motion-orbit motion-orbit-a"></div>
                <div class="motion-orbit motion-orbit-b"></div>
                <div class="motion-ball" data-motion-ball>
                    @for ($i = 0; $i < 12; $i++)<i></i>@endfor
                </div>
                <div class="motion-badge motion-badge-a">Choose date</div>
                <div class="motion-badge motion-badge-b">Reserve direct</div>
                <div class="motion-shadow"></div>
            </div>
        </div>
        <div class="ball-divider" aria-hidden="true">
            @for ($i = 0; $i < 18; $i++)<span></span>@endfor
        </div>
    </section>

    <section class="quick-stat-section">
        <div class="site-container quick-stats">
            <div class="reveal"><strong>{{ $courtCount }}</strong><span>verified courts live</span></div>
            <div class="reveal"><strong>60</strong><span>days of visible schedules</span></div>
            <div class="reveal"><strong>100%</strong><span>Kabacan-only discovery</span></div>
            <div class="reveal"><strong>PHP</strong><span>clear local pricing</span></div>
        </div>
    </section>

    <section class="section-pad bg-sand">
        <div class="site-container">
            <div class="section-heading reveal">
                <div>
                    <p class="eyebrow">Verified court directory</p>
                    <h2>Real venues. Clear details.<br>No made-up listings.</h2>
                </div>
                <a href="{{ route('courts.index') }}" class="btn-outline">Explore every court</a>
            </div>

            @if ($featuredCourts->isNotEmpty())
                <div class="court-card-grid mt-10">
                    @foreach ($featuredCourts as $court)
                        <x-court-card :court="$court" />
                    @endforeach
                </div>
            @else
                <div class="verified-empty reveal mt-10">
                    <div class="verified-empty-mark">
                        <img src="{{ asset('images/kabacan-pickleplay-mark.svg') }}" alt="">
                    </div>
                    <div>
                        <p class="eyebrow">Verification in progress</p>
                        <h3>Kabacan courts are being checked before they go live.</h3>
                        <p>The USM Outdoor Pickle Ball Court is preloaded for administrator review. Rates, schedules, contacts, and reusable photos will appear only after confirmation.</p>
                    </div>
                    <a href="{{ route('register') }}" class="btn-primary">Register a venue</a>
                </div>
            @endif
        </div>
    </section>

    <section class="section-pad booking-story">
        <div class="site-container">
            <div class="section-heading section-heading-light reveal">
                <div>
                    <p class="eyebrow eyebrow-light">One clean booking flow</p>
                    <h2>From map pin to match point.</h2>
                </div>
                <p>Availability comes from each court owner’s schedule. Every reservation is checked again on the server before it is accepted.</p>
            </div>
            <div class="story-grid mt-12">
                @foreach ([
                    ['01', 'Discover', 'Search verified Kabacan courts on the map and compare real venue details.'],
                    ['02', 'Choose', 'Pick a date, playable court, and available time with transparent PHP pricing.'],
                    ['03', 'Confirm', 'Send the reservation directly to the owner and follow payment status in your account.'],
                    ['04', 'Play', 'Receive status updates, manage cancellations, and review only after a completed booking.'],
                ] as [$number, $title, $copy])
                    <article class="story-card reveal">
                        <span>{{ $number }}</span>
                        <div class="story-icon" aria-hidden="true"></div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-pad bg-white">
        <div class="site-container trust-layout">
            <div class="trust-art reveal" aria-hidden="true">
                <div class="trust-pin">K</div>
                <span class="trust-ring trust-ring-a"></span>
                <span class="trust-ring trust-ring-b"></span>
                <span class="trust-dot trust-dot-a"></span>
                <span class="trust-dot trust-dot-b"></span>
                <span class="trust-dot trust-dot-c"></span>
            </div>
            <div class="reveal">
                <p class="eyebrow">Trust before traffic</p>
                <h2 class="display-sm mt-3">A court is published only when the important facts are complete.</h2>
                <div class="trust-checks">
                    <div><b>01</b><span><strong>Actual photos</strong><small>Uploaded with rights confirmation.</small></span></div>
                    <div><b>02</b><span><strong>Location evidence</strong><small>Official source, owner, Maps, or field check.</small></span></div>
                    <div><b>03</b><span><strong>Bookable schedule</strong><small>Rates and time slots come from the venue.</small></span></div>
                    <div><b>04</b><span><strong>Verified reviews</strong><small>Only players with completed bookings can post.</small></span></div>
                </div>
            </div>
        </div>
    </section>

    @if ($posts->isNotEmpty())
        <section class="section-pad bg-sand">
            <div class="site-container">
                <div class="section-heading reveal">
                    <div><p class="eyebrow">Around the courts</p><h2>Play updates from Kabacan.</h2></div>
                    <a class="btn-outline" href="{{ route('content.index') }}">All updates</a>
                </div>
                <div class="post-grid mt-10">
                    @foreach ($posts as $post)
                        <article class="post-card reveal">
                            <span>{{ ucfirst($post->type) }}</span>
                            <h3><a href="{{ route('content.show', $post) }}">{{ $post->title }}</a></h3>
                            <p>{{ $post->excerpt ?: Str::limit($post->body, 150) }}</p>
                            <a href="{{ route('content.show', $post) }}">Read update ↗</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="owner-cta">
        <div class="site-container owner-cta-inner reveal">
            <div>
                <p class="eyebrow eyebrow-light">Run a Kabacan court?</p>
                <h2>Put your real schedule where players can find it.</h2>
                <p>Apply for a verified owner account, publish accurate venue information, and manage every reservation directly.</p>
            </div>
            <a class="btn-lime" href="{{ route('register') }}">Start owner verification</a>
        </div>
    </section>
@endsection

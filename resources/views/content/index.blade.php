@extends('layouts.public')

@section('title', 'Kabacan pickleball updates — Kabacan PicklePlay')
@section('description', 'Announcements, promotions, tournaments, and court maintenance notices from Kabacan PicklePlay.')

@section('content')
    <section class="simple-hero">
        <div class="site-container py-16 sm:py-24">
            <p class="eyebrow eyebrow-light">Kabacan court community</p>
            <h1>Updates worth<br><span>showing up for.</span></h1>
            <p>Announcements, promotions, tournaments, and maintenance notices published by verified venue teams.</p>
        </div>
    </section>
    <section class="section-pad bg-sand">
        <div class="site-container">
            <div class="content-tabs">
                <a class="{{ ! $type ? 'is-active' : '' }}" href="{{ route('content.index') }}">All</a>
                @foreach (['announcement','promotion','tournament','maintenance'] as $item)
                    <a class="{{ $type === $item ? 'is-active' : '' }}" href="{{ route('content.index', ['type' => $item]) }}">{{ ucfirst($item) }}</a>
                @endforeach
            </div>
            <div class="post-grid mt-10">
                @forelse ($posts as $post)
                    <article class="post-card reveal">
                        <span>{{ ucfirst($post->type) }}</span>
                        <h2><a href="{{ route('content.show', $post) }}">{{ $post->title }}</a></h2>
                        <p>{{ $post->excerpt ?: Str::limit($post->body, 180) }}</p>
                        @if ($post->court)<small>{{ $post->court->name }}</small>@endif
                        <a href="{{ route('content.show', $post) }}">Read update ↗</a>
                    </article>
                @empty
                    <div class="directory-empty col-span-full"><h2>No published updates yet.</h2><p>Verified court teams can publish local announcements from their dashboard.</p></div>
                @endforelse
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>

            <div id="verification" class="policy-grid mt-20">
                <article><p class="eyebrow">Verification policy</p><h2>Evidence before publication.</h2><p>Venue facts must be backed by an official page, a court owner, Google Maps, or recorded field verification. Missing rates, contacts, amenities, photos, and schedules are never guessed.</p></article>
                <article id="privacy"><p class="eyebrow">Privacy</p><h2>Private files stay private.</h2><p>Payment proofs and owner evidence are kept outside public storage and can be accessed only by the submitting user, assigned court managers, or administrators.</p></article>
                <article id="terms"><p class="eyebrow">Platform terms</p><h2>Owners control venue operations.</h2><p>Kabacan PicklePlay records reservations and payment status. Court owners remain responsible for their schedules, cancellation decisions, on-site policies, and manually handled refunds.</p></article>
            </div>
        </div>
    </section>
@endsection

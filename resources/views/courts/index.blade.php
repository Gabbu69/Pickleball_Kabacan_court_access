@extends('layouts.public')

@section('title', 'Find verified Kabacan pickleball courts — Kabacan PicklePlay')
@section('description', 'Search verified Kabacan pickleball courts by barangay, rate, availability, court type, and amenities.')

@php
    $mapData = $courts->filter(fn ($court) => $court->latitude && $court->longitude)->map(fn ($court) => [
        'name' => $court->name,
        'url' => route('courts.show', $court),
        'lat' => (float) $court->latitude,
        'lng' => (float) $court->longitude,
        'environment' => $court->environment,
        'barangay' => $court->barangay,
    ])->values();
@endphp

@section('content')
    <section class="directory-hero">
        <div class="site-container py-14 sm:py-20">
            <p class="eyebrow eyebrow-light reveal">Kabacan court directory</p>
            <div class="mt-3 grid gap-6 lg:grid-cols-[1fr_0.55fr] lg:items-end">
                <h1 class="reveal">Your next rally<br>starts <span>nearby.</span></h1>
                <p class="reveal text-base font-semibold leading-8 text-white/65">Every public listing has passed the platform’s completion and verification checks.</p>
            </div>
        </div>
    </section>

    <section class="directory-section">
        <div class="site-container py-8 sm:py-12">
            <form method="GET" action="{{ route('courts.index') }}" class="filter-panel reveal">
                <div class="filter-search">
                    <label for="q">Search</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Court or barangay">
                </div>
                <div>
                    <label for="barangay">Barangay</label>
                    <select id="barangay" name="barangay">
                        <option value="">Everywhere</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay }}" @selected(($filters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="environment">Court type</label>
                    <select id="environment" name="environment">
                        <option value="">Indoor & outdoor</option>
                        <option value="indoor" @selected(($filters['environment'] ?? '') === 'indoor')>Indoor</option>
                        <option value="outdoor" @selected(($filters['environment'] ?? '') === 'outdoor')>Outdoor</option>
                    </select>
                </div>
                <div>
                    <label for="amenity">Amenity</label>
                    <select id="amenity" name="amenity">
                        <option value="">Any amenity</option>
                        @foreach ($amenities as $amenity)
                            <option value="{{ $amenity->slug }}" @selected(($filters['amenity'] ?? '') === $amenity->slug)>{{ $amenity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="min_price">Minimum price</label>
                    <input id="min_price" type="number" name="min_price" min="0" step="1" inputmode="decimal" value="{{ $filters['min_price'] ?? '' }}" placeholder="₱ Min">
                </div>
                <div>
                    <label for="max_price">Maximum price</label>
                    <input id="max_price" type="number" name="max_price" min="0" step="1" inputmode="decimal" value="{{ $filters['max_price'] ?? '' }}" placeholder="₱ Max">
                </div>
                <div>
                    <label for="date">Available date</label>
                    <input id="date" type="date" name="date" min="{{ now()->format('Y-m-d') }}" max="{{ now()->addDays(60)->format('Y-m-d') }}" value="{{ $filters['date'] ?? '' }}">
                </div>
                <button class="btn-primary justify-center">Apply filters</button>
            </form>

            <div class="directory-toolbar">
                <p><strong>{{ $courts->count() }}</strong> verified {{ Str::plural('court', $courts->count()) }}</p>
                <div class="view-toggle" x-data="{ view: 'list' }">
                    <button type="button" @click="view = 'list'; document.body.dataset.directoryView = 'list'" :class="{ 'is-active': view === 'list' }">List</button>
                    <button type="button" @click="view = 'map'; document.body.dataset.directoryView = 'map'" :class="{ 'is-active': view === 'map' }">Map</button>
                </div>
            </div>

            <script type="application/json" id="court-map-data">@json($mapData)</script>
            <div class="directory-layout">
                <div class="directory-list">
                    @forelse ($courts as $court)
                        <x-court-card :court="$court" />
                    @empty
                        <div class="directory-empty">
                            <img src="{{ asset('images/kabacan-pickleplay-mark.svg') }}" alt="">
                            <h2>No verified court matches yet.</h2>
                            <p>Try fewer filters, or check again when owners finish verification.</p>
                            <a href="{{ route('courts.index') }}" class="btn-outline">Clear filters</a>
                        </div>
                    @endforelse
                </div>
                <aside class="directory-map-wrap">
                    <div id="court-map" class="directory-map" data-court-map>
                        <div class="map-empty-state">Verified map pins will appear here.</div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection

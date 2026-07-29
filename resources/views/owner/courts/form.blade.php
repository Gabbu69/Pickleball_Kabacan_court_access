<x-app-layout>
    <x-slot name="header">
        <div><a href="{{ route('owner.courts.index') }}" class="back-link">← Courts</a><p class="eyebrow mt-4">Verified venue information</p><h1 class="dashboard-title">{{ $court->exists ? 'Edit '.$court->name : 'Create a court draft' }}</h1></div>
    </x-slot>

    <form method="POST" action="{{ $court->exists ? route('owner.courts.update', $court) : route('owner.courts.store') }}" class="panel space-y-8">
        @csrf
        @if ($court->exists) @method('PUT') @endif

        <section>
            <div class="form-section-heading"><span>01</span><div><h2>Identity and description</h2><p>Use the official or owner-confirmed venue name.</p></div></div>
            <div class="form-grid mt-6">
                <div class="sm:col-span-2"><label for="court-name">Court name</label><input id="court-name" class="form-input" name="name" value="{{ old('name', $court->name) }}" required></div>
                <div class="sm:col-span-2"><label for="court-short-description">Short description</label><input id="court-short-description" class="form-input" name="short_description" maxlength="320" value="{{ old('short_description', $court->short_description) }}"></div>
                <div class="sm:col-span-2"><label for="court-description">Full description</label><textarea id="court-description" class="form-input min-h-32" name="description">{{ old('description', $court->description) }}</textarea></div>
                <div><label for="court-environment">Environment</label><select id="court-environment" class="form-input" name="environment"><option value="outdoor" @selected(old('environment', $court->environment) === 'outdoor')>Outdoor</option><option value="indoor" @selected(old('environment', $court->environment) === 'indoor')>Indoor</option></select></div>
                <div><label for="court-venue-type">Venue type</label><select id="court-venue-type" class="form-input" name="venue_type"><option value="dedicated" @selected(old('venue_type', $court->venue_type) === 'dedicated')>Dedicated pickleball venue</option><option value="multipurpose" @selected(old('venue_type', $court->venue_type) === 'multipurpose')>Multipurpose court</option></select></div>
            </div>
        </section>

        <section>
            <div class="form-section-heading"><span>02</span><div><h2>Kabacan location</h2><p>Coordinates and directions must point to the real venue.</p></div></div>
            <div class="form-grid mt-6">
                <div class="sm:col-span-2"><label for="court-address-line">Address line</label><input id="court-address-line" class="form-input" name="address_line" value="{{ old('address_line', $court->address_line) }}" required></div>
                <div><label for="court-barangay">Barangay</label><input id="court-barangay" class="form-input" name="barangay" value="{{ old('barangay', $court->barangay) }}"></div>
                <div><label for="court-postal-code">Postal code</label><input id="court-postal-code" class="form-input" name="postal_code" value="{{ old('postal_code', $court->postal_code ?: '9407') }}" required></div>
                <div><label for="court-municipality">Municipality</label><input id="court-municipality" class="form-input" name="municipality" value="Kabacan" readonly></div>
                <div><label for="court-province">Province</label><input id="court-province" class="form-input" name="province" value="Cotabato" readonly></div>
                <div><label for="court-latitude">Latitude</label><input id="court-latitude" class="form-input" type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $court->latitude) }}"></div>
                <div><label for="court-longitude">Longitude</label><input id="court-longitude" class="form-input" type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $court->longitude) }}"></div>
                <div class="sm:col-span-2">
                    <p id="court-map-picker-label" class="form-label">Map pin</p>
                    <div
                        class="map-picker"
                        data-map-picker
                        data-lat="{{ old('latitude', $court->latitude) }}"
                        data-lng="{{ old('longitude', $court->longitude) }}"
                        role="application"
                        aria-labelledby="court-map-picker-label"
                    ><span>Loading the Kabacan map…</span></div>
                    <small class="form-help">Click the real venue location or drag the marker. The coordinates above update automatically and still require evidence.</small>
                </div>
                <div class="sm:col-span-2"><label for="court-google-maps-url">Verified Google Maps directions URL</label><input id="court-google-maps-url" class="form-input" type="url" name="google_maps_url" value="{{ old('google_maps_url', $court->google_maps_url) }}"></div>
            </div>
        </section>

        <section>
            <div class="form-section-heading"><span>03</span><div><h2>Contact and booking policy</h2><p>Leave unknown contact channels blank; never guess them.</p></div></div>
            <div class="form-grid mt-6">
                <div><label for="court-phone">Public phone</label><input id="court-phone" class="form-input" name="phone" value="{{ old('phone', $court->phone) }}"></div>
                <div><label for="court-email">Public email</label><input id="court-email" class="form-input" type="email" name="email" value="{{ old('email', $court->email) }}"></div>
                <div class="sm:col-span-2"><label for="court-facebook-url">Official Facebook URL</label><input id="court-facebook-url" class="form-input" type="url" name="facebook_url" value="{{ old('facebook_url', $court->facebook_url) }}"></div>
                <div><label for="court-payment-policy">Payment policy</label><select id="court-payment-policy" class="form-input" name="payment_policy"><option value="pay_on_site" @selected(old('payment_policy', $court->payment_policy) === 'pay_on_site')>Pay at venue</option><option value="proof_required" @selected(old('payment_policy', $court->payment_policy) === 'proof_required')>Verified prepayment required</option><option value="either" @selected(old('payment_policy', $court->payment_policy) === 'either')>Prepay or pay at venue</option></select></div>
                <div><label for="court-cancellation-cutoff">Cancellation cutoff (hours)</label><input id="court-cancellation-cutoff" class="form-input" type="number" min="0" max="168" name="cancellation_cutoff_hours" value="{{ old('cancellation_cutoff_hours', $court->cancellation_cutoff_hours ?: 4) }}" required></div>
            </div>
        </section>

        <section id="court-amenities">
            <div class="form-section-heading"><span>04</span><div><h2>Verified amenities</h2><p>Select only what players can actually use.</p></div></div>
            <div class="checkbox-grid mt-6">
                @foreach ($amenities as $amenity)
                    <label><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, old('amenities', $court->exists ? $court->amenities->pluck('id')->all() : [])))><span>{{ $amenity->name }}</span></label>
                @endforeach
            </div>
        </section>

        <div class="form-actions"><a class="btn-quiet" href="{{ route('owner.courts.index') }}">Cancel</a><button class="btn-primary">{{ $court->exists ? 'Save court details' : 'Create draft' }}</button></div>
    </form>
</x-app-layout>

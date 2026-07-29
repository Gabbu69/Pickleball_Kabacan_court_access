<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><a href="{{ route('owner.courts.index') }}" class="back-link">← Courts</a><p class="eyebrow mt-4">Venue control room</p><h1 class="dashboard-title">{{ $court->name }}</h1></div>
            <div class="flex flex-wrap gap-2"><span class="status status-lg status-{{ $court->status->value }}">{{ str_replace('_', ' ', ucfirst($court->status->value)) }}</span><a class="btn-outline" href="{{ route('owner.courts.edit', $court) }}">Edit listing</a></div>
        </div>
    </x-slot>

    <section id="setup-publication" class="readiness-panel {{ $publishabilityErrors ? 'has-errors' : 'is-ready' }}">
        <div>
            <p class="eyebrow">Publication readiness</p>
            <h2>{{ $publishabilityErrors ? count($publishabilityErrors).' items still required' : 'Complete and ready for administrator review' }}</h2>
            @if ($publishabilityErrors)<p>{{ implode(' · ', $publishabilityErrors) }}</p>@else<p>All publication requirements are present. An administrator still reviews evidence and public accuracy.</p>@endif
        </div>
        @if ($court->status->value !== 'published')
            <form method="POST" action="{{ route('owner.courts.submit', $court) }}">@csrf @method('PATCH')<button class="btn-primary">Submit for verification</button></form>
        @endif
    </section>

    @php
        $acceptedFacts = $court->verificationClaims
            ->where('status', 'accepted')
            ->whereNull('invalidated_at')
            ->pluck('field_key');
        $identityComplete = filled($court->name)
            && filled($court->address_line)
            && filled($court->latitude)
            && filled($court->longitude);
        $amenitiesComplete = $court->amenities->isNotEmpty();
    @endphp
    <nav class="setup-stepper" aria-label="Court setup progress">
        @foreach ([
            ['setup-identity', 'Identity & map', $identityComplete],
            ['setup-media', 'Media', $court->photos->isNotEmpty()],
            ['setup-amenities', 'Amenities', $amenitiesComplete],
            ['setup-courts', 'Courts & rates', $court->units->isNotEmpty() && $court->scheduleRules()->where('is_active', true)->exists()],
            ['setup-hours', 'Hours', $court->operatingHours->where('is_closed', false)->isNotEmpty()],
            ['setup-availability', 'Availability', true],
            ['setup-payment', 'Payment', $court->payment_policy === 'pay_on_site' || $court->paymentMethods->where('is_active', true)->isNotEmpty()],
            ['setup-verification', 'Verification', $acceptedFacts->count() === count(\App\Models\CourtVerificationClaim::REQUIRED_FIELDS)],
            ['setup-publication', 'Publication', $court->status->value === 'published'],
        ] as $index => [$anchor, $label, $complete])
            <a href="#{{ $anchor }}" class="{{ $complete ? 'is-complete' : '' }}">
                <span>{{ $complete ? '✓' : $index + 1 }}</span>
                <strong>{{ $label }}</strong>
            </a>
        @endforeach
    </nav>

    <div class="manage-grid mt-7">
        <div class="space-y-6">
            <details id="setup-identity" class="manage-section" open>
                <summary><span>01</span><div><strong>Identity and map</strong><small>{{ $identityComplete ? 'Core location recorded' : 'Location details required' }}</small></div><i>＋</i></summary>
                <div class="manage-content">
                    <div class="setup-summary-grid">
                        <div><small>Official name</small><strong>{{ $court->name }}</strong></div>
                        <div><small>Kabacan address</small><strong>{{ $court->full_address }}</strong></div>
                        <div><small>Coordinates</small><strong>{{ filled($court->latitude) && filled($court->longitude) ? $court->latitude.', '.$court->longitude : 'Not yet pinned' }}</strong></div>
                        <div><small>Public contact</small><strong>{{ $court->phone ?: ($court->email ?: 'Not yet supplied') }}</strong></div>
                    </div>
                    <a class="btn-outline mt-5 w-fit" href="{{ route('owner.courts.edit', $court) }}">Edit identity, map, and contact</a>
                </div>
            </details>

            <details id="setup-media" class="manage-section" open>
                <summary><span>02</span><div><strong>Actual court photos</strong><small>{{ $court->photos->count() }} uploaded</small></div><i>＋</i></summary>
                <div class="manage-content">
                    <div class="photo-manage-grid">
                        @foreach ($court->photos as $photo)
                            <figure><img src="{{ $photo->public_url }}" alt="{{ $photo->alt_text }}"><figcaption>{{ $photo->is_primary ? 'Primary · ' : '' }}{{ $photo->caption }}</figcaption><form method="POST" action="{{ route('owner.courts.photos.destroy', [$court, $photo]) }}">@csrf @method('DELETE')<button class="text-danger">Remove</button></form></figure>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('owner.courts.photos.store', $court) }}" enctype="multipart/form-data" class="form-grid mt-5">
                        @csrf
                        <div><label for="court-photo">Photo <span>(maximum 4 MB)</span></label><input id="court-photo" class="form-input" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" required></div>
                        <div><label for="court-photo-alt">Accessible description</label><input id="court-photo-alt" class="form-input" name="alt_text" required></div>
                        <div><label for="court-photo-caption">Caption</label><input id="court-photo-caption" class="form-input" name="caption"></div>
                        <div class="flex flex-col justify-end gap-2"><label class="check-line"><input type="checkbox" name="is_primary" value="1"> Primary photo</label><label class="check-line"><input type="checkbox" name="rights_confirmed" value="1" required> I have permission to publish this actual photo.</label></div>
                        <button class="btn-primary w-fit">Upload photo</button>
                    </form>
                </div>
            </details>

            <details id="setup-amenities" class="manage-section">
                <summary><span>03</span><div><strong>Verified amenities</strong><small>{{ $court->amenities->count() }} selected</small></div><i>＋</i></summary>
                <div class="manage-content">
                    @if ($court->amenities->isNotEmpty())
                        <div class="amenity-list">
                            @foreach ($court->amenities as $amenity)
                                <span>{{ $amenity->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-600">No amenities are claimed. Leave this empty until the available facilities are confirmed.</p>
                    @endif
                    <a class="btn-outline mt-5 w-fit" href="{{ route('owner.courts.edit', $court) }}#court-amenities">Edit verified amenities</a>
                </div>
            </details>

            <details id="setup-courts" class="manage-section" open>
                <summary><span>04</span><div><strong>Playable courts and rates</strong><small>{{ $court->units->count() }} units</small></div><i>＋</i></summary>
                <div class="manage-content">
                    @foreach ($court->units as $unit)
                        <article class="unit-card">
                            <div class="flex items-center justify-between gap-3"><div><strong>{{ $unit->name }}</strong><small>{{ ucfirst($unit->environment ?: $court->environment) }} · {{ $unit->is_active ? 'Active' : 'Inactive' }}</small></div><form method="POST" action="{{ route('owner.courts.units.destroy', [$court, $unit]) }}">@csrf @method('DELETE')<button class="text-danger">Remove</button></form></div>
                            <div class="schedule-list">
                                @foreach ($unit->scheduleRules as $rule)
                                    <div class="{{ $rule->is_active ? '' : 'is-inactive' }}"><span>{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$rule->day_of_week] }} · {{ \Carbon\Carbon::parse($rule->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($rule->ends_at)->format('g:i A') }} · {{ $rule->slot_minutes }} min</span><strong>₱{{ number_format($rule->price_centavos / 100, 2) }}</strong>@if($rule->is_active)<form method="POST" action="{{ route('owner.courts.schedules.destroy', [$court, $rule]) }}">@csrf @method('DELETE')<button>Deactivate</button></form>@endif</div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                    <form method="POST" action="{{ route('owner.courts.units.store', $court) }}" class="inline-form mt-5">
                        @csrf
                        <div><label for="unit-name">Playable court name</label><input id="unit-name" class="form-input" name="name" placeholder="Court 1" required></div>
                        <div><label for="unit-environment">Court environment</label><select id="unit-environment" class="form-input" name="environment"><option value="">Use venue type</option><option value="indoor">Indoor</option><option value="outdoor">Outdoor</option></select></div>
                        <input type="hidden" name="is_active" value="1">
                        <button class="btn-outline">Add playable court</button>
                    </form>
                    @if ($court->units->isNotEmpty())
                        <form method="POST" action="{{ route('owner.courts.schedules.store', $court) }}" class="form-grid mt-7 border-t border-slate-200 pt-6">
                            @csrf
                            <div><label for="schedule-unit">Playable court</label><select id="schedule-unit" class="form-input" name="court_unit_id">@foreach($court->units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></div>
                            <div><label for="schedule-day">Day</label><select id="schedule-day" class="form-input" name="day_of_week">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i=>$day)<option value="{{ $i }}">{{ $day }}</option>@endforeach</select></div>
                            <div><label for="schedule-start">Window starts</label><input id="schedule-start" class="form-input" type="time" name="starts_at" required></div>
                            <div><label for="schedule-end">Window ends</label><input id="schedule-end" class="form-input" type="time" name="ends_at" required></div>
                            <div><label for="schedule-length">Slot length</label><select id="schedule-length" class="form-input" name="slot_minutes"><option value="30">30 minutes</option><option value="60" selected>60 minutes</option><option value="90">90 minutes</option><option value="120">120 minutes</option></select></div>
                            <div><label for="schedule-price">Rate in PHP</label><input id="schedule-price" class="form-input" type="number" name="price" min="0" step="0.01" required></div>
                            <div><label for="schedule-valid-from">Valid from <span>(optional)</span></label><input id="schedule-valid-from" class="form-input" type="date" name="valid_from"></div>
                            <div><label for="schedule-valid-until">Valid until <span>(optional)</span></label><input id="schedule-valid-until" class="form-input" type="date" name="valid_until"></div>
                            <button class="btn-primary w-fit">Add schedule rule</button>
                        </form>
                    @endif
                </div>
            </details>

            <details id="setup-hours" class="manage-section">
                <summary><span>05</span><div><strong>Operating hours</strong><small>Public weekly schedule</small></div><i>＋</i></summary>
                <div class="manage-content">
                    <form method="POST" action="{{ route('owner.courts.hours.update', $court) }}">@csrf @method('PUT')
                        <div class="hours-editor">
                            @foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $index => $day)
                                @php $hours = $court->operatingHours->firstWhere('day_of_week', $index); @endphp
                                <div>
                                    <strong>{{ $day }}</strong>
                                    <label class="sr-only" for="hours-open-{{ $index }}">{{ $day }} opening time</label>
                                    <input id="hours-open-{{ $index }}" type="time" name="hours[{{ $index }}][opens_at]" value="{{ $hours?->opens_at ? substr($hours->opens_at, 0, 5) : '' }}">
                                    <span>to</span>
                                    <label class="sr-only" for="hours-close-{{ $index }}">{{ $day }} closing time</label>
                                    <input id="hours-close-{{ $index }}" type="time" name="hours[{{ $index }}][closes_at]" value="{{ $hours?->closes_at ? substr($hours->closes_at, 0, 5) : '' }}">
                                    <label><input type="checkbox" name="hours[{{ $index }}][is_closed]" value="1" @checked($hours?->is_closed)> Closed</label>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn-primary mt-5">Save operating hours</button>
                    </form>
                </div>
            </details>

            <details id="setup-availability" class="manage-section">
                <summary><span>06</span><div><strong>Maintenance and blocked dates</strong><small>{{ $court->blackouts->count() }} blocks</small></div><i>＋</i></summary>
                <div class="manage-content">
                    <div class="compact-list">@foreach($court->blackouts as $blackout)<div><span><strong>{{ $blackout->reason }}</strong><small>{{ $blackout->starts_at->format('M j, g:i A') }} – {{ $blackout->ends_at->format('M j, g:i A') }}{{ $blackout->courtUnit ? ' · '.$blackout->courtUnit->name : ' · All courts' }}</small></span><form method="POST" action="{{ route('owner.courts.blackouts.destroy', [$court,$blackout]) }}">@csrf @method('DELETE')<button class="text-danger">Remove</button></form></div>@endforeach</div>
                    <form method="POST" action="{{ route('owner.courts.blackouts.store', $court) }}" class="form-grid mt-6">
                        @csrf
                        <div><label for="blackout-unit">Playable court</label><select id="blackout-unit" class="form-input" name="court_unit_id"><option value="">All courts</option>@foreach($court->units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></div>
                        <div><label for="blackout-reason">Reason</label><input id="blackout-reason" class="form-input" name="reason" required></div>
                        <div><label for="blackout-start">Starts</label><input id="blackout-start" class="form-input" type="datetime-local" name="starts_at" required></div>
                        <div><label for="blackout-end">Ends</label><input id="blackout-end" class="form-input" type="datetime-local" name="ends_at" required></div>
                        <label class="check-line"><input type="checkbox" name="is_public" value="1" checked> Show reason publicly</label>
                        <button class="btn-primary w-fit">Block schedule</button>
                    </form>
                </div>
            </details>
        </div>

        <aside class="space-y-6">
            <section id="setup-payment" class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Payment methods</p><h2>Owner-provided details</h2></div></div>
                <div class="compact-list">@foreach($court->paymentMethods as $method)<div><span><strong>{{ $method->label }}</strong><small>{{ $method->account_name }} {{ $method->account_reference }}</small></span>@if($method->is_active)<form method="POST" action="{{ route('owner.courts.payment-methods.destroy', [$court,$method]) }}">@csrf @method('DELETE')<button class="text-danger">Disable</button></form>@endif</div>@endforeach</div>
                <form method="POST" action="{{ route('owner.courts.payment-methods.store', $court) }}" class="space-y-3 mt-5">
                    @csrf
                    <div><label for="payment-type">Method type</label><select id="payment-type" class="form-input" name="type"><option value="gcash">GCash</option><option value="bank">Bank transfer</option><option value="cash">Cash</option><option value="other">Other</option></select></div>
                    <div><label for="payment-label">Public method label</label><input id="payment-label" class="form-input" name="label" placeholder="Example: Venue GCash" required></div>
                    <div><label for="payment-account-name">Verified account name</label><input id="payment-account-name" class="form-input" name="account_name"></div>
                    <div><label for="payment-reference">Number or account reference</label><input id="payment-reference" class="form-input" name="account_reference"></div>
                    <div><label for="payment-instructions">Payment instructions</label><textarea id="payment-instructions" class="form-input" name="instructions"></textarea></div>
                    <button class="btn-outline">Add method</button>
                </form>
            </section>

            <section id="setup-verification" class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Verification evidence</p><h2>Sources and field proof</h2></div></div>
                <div class="verification-matrix">
                    @foreach (\App\Models\CourtVerificationClaim::REQUIRED_FIELDS as $key => $label)
                        <div class="{{ $acceptedFacts->contains($key) ? 'is-verified' : '' }}"><span>{{ $acceptedFacts->contains($key) ? '✓' : '○' }}</span><strong>{{ $label }}</strong><small>{{ $acceptedFacts->contains($key) ? 'Verified' : 'Evidence required' }}</small></div>
                    @endforeach
                </div>
                <div class="verification-list">
                    @foreach($court->verifications as $verification)
                        <div>
                            <span class="status status-{{ $verification->status }}">{{ ucfirst($verification->status) }}</span>
                            <strong>{{ str_replace('_',' ',ucfirst($verification->type)) }}</strong>
                            <p>{{ $verification->notes }}</p>
                            <small>{{ $verification->claims->pluck('field_key')->map(fn($field) => \App\Models\CourtVerificationClaim::REQUIRED_FIELDS[$field] ?? $field)->implode(' · ') }}</small>
                            @if($verification->source_url)<a href="{{ $verification->source_url }}" target="_blank" rel="noopener">Open source ↗</a>@endif
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('owner.courts.verifications.store', $court) }}" enctype="multipart/form-data" class="space-y-4 mt-5">
                    @csrf
                    <div><label for="verification-type">Evidence source</label><select id="verification-type" class="form-input" name="type"><option value="official_page">Official page</option><option value="court_owner">Court owner confirmation</option><option value="google_maps">Google Maps</option><option value="field_verification">Field verification</option></select></div>
                    <fieldset>
                        <legend>Facts confirmed by this evidence</legend>
                        <div class="claim-check-grid">
                            @foreach(\App\Models\CourtVerificationClaim::REQUIRED_FIELDS as $key => $label)
                                <label><input type="checkbox" name="facts[]" value="{{ $key }}"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div><label for="verification-url">Source URL <span>(optional when a file is supplied)</span></label><input id="verification-url" class="form-input" type="url" name="source_url"></div>
                    <div><label for="verification-notes">What does this source confirm?</label><textarea id="verification-notes" class="form-input min-h-28" name="notes" required></textarea></div>
                    <div><label for="verification-file">Evidence file <span>(maximum 4 MB)</span></label><input id="verification-file" class="form-input" type="file" name="evidence" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
                    <button class="btn-primary">Submit fact-specific evidence</button>
                </form>
            </section>

            <section class="panel panel-danger">
                <p class="eyebrow">Archive listing</p><p class="mt-2 text-sm text-slate-600">Archiving removes the court from public discovery without deleting its reservation history.</p>
                <form method="POST" action="{{ route('owner.courts.archive', $court) }}" class="mt-4">@csrf @method('PATCH')<button class="btn-danger">Archive court</button></form>
            </section>
        </aside>
    </div>
</x-app-layout>

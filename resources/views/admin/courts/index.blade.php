<x-app-layout>
    <x-slot name="header">
        <div><p class="eyebrow">Verification and publication</p><h1 class="dashboard-title">Court review queue</h1></div>
    </x-slot>

    <div class="space-y-6">
        @forelse ($courts as $court)
            @php $missing = $court->publishabilityErrors(); @endphp
            <article class="panel admin-court-card">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><span class="status status-{{ $court->status->value }}">{{ str_replace('_',' ',ucfirst($court->status->value)) }}</span><span class="status status-{{ $court->verification_status }}">{{ ucfirst($court->verification_status) }}</span>@if($court->is_featured)<span class="status status-featured">Featured</span>@endif</div>
                        <h2>{{ $court->name }}</h2>
                        <p>{{ $court->full_address }}</p>
                        <small>{{ $court->units_count }} units · {{ $court->photos_count }} photos · {{ $court->bookings_count }} bookings · Managers: {{ $court->managers->pluck('name')->implode(', ') ?: 'Unassigned' }}</small>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn-outline" href="{{ route('owner.courts.manage', $court) }}">Inspect listing</a>
                        @if (! $missing)
                            <form method="POST" action="{{ route('admin.courts.publish', $court) }}">@csrf @method('PATCH')<button class="btn-primary">Publish verified court</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.courts.feature', $court) }}">@csrf @method('PATCH')<input type="hidden" name="is_featured" value="{{ $court->is_featured ? 0 : 1 }}"><button class="btn-quiet">{{ $court->is_featured ? 'Remove feature' : 'Feature' }}</button></form>
                        <form method="POST" action="{{ route('admin.courts.archive', $court) }}">@csrf @method('PATCH')<button class="btn-danger">Archive</button></form>
                    </div>
                </div>
                @if ($missing)
                    <div class="missing-list"><strong>Cannot publish yet</strong><p>{{ implode(' · ', $missing) }}</p></div>
                @endif
                <div class="evidence-review-list">
                    @forelse ($court->verifications as $verification)
                        <div>
                            <div><span class="status status-{{ $verification->status }}">{{ ucfirst($verification->status) }}</span><strong>{{ str_replace('_',' ',ucfirst($verification->type)) }}</strong><p>{{ $verification->notes }}</p><div class="flex gap-3">@if($verification->source_url)<a href="{{ $verification->source_url }}" target="_blank" rel="noopener">Open source ↗</a>@endif @if($verification->evidence_path)<a href="{{ route('admin.verifications.evidence', $verification) }}">Download private evidence</a>@endif</div></div>
                            @if ($verification->status === 'pending')
                                <div class="evidence-actions">
                                    <form method="POST" action="{{ route('admin.verifications.accept', $verification) }}">@csrf @method('PATCH')<input class="form-input" name="notes" placeholder="Optional reviewer note"><button class="btn-primary">Accept evidence</button></form>
                                    <form method="POST" action="{{ route('admin.verifications.reject', $verification) }}">@csrf @method('PATCH')<input class="form-input" name="notes" placeholder="Required rejection reason" required><button class="btn-danger">Reject</button></form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No verification evidence submitted.</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="panel panel-empty"><h2>No court drafts exist.</h2></div>
        @endforelse
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="eyebrow">Venue management</p><h1 class="dashboard-title">Courts and listings</h1></div>
            <a class="btn-primary" href="{{ route('owner.courts.create') }}">Create court draft</a>
        </div>
    </x-slot>

    <div class="panel">
        <div class="data-cards">
            @forelse ($courts as $court)
                <article>
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="eyebrow">{{ $court->barangay ?: 'Kabacan' }}</p><h2>{{ $court->name }}</h2></div>
                        <span class="status status-{{ $court->status->value }}">{{ str_replace('_', ' ', ucfirst($court->status->value)) }}</span>
                    </div>
                    <p>{{ $court->full_address }}</p>
                    <div class="mini-metrics"><span><strong>{{ $court->units_count }}</strong> playable courts</span><span><strong>{{ $court->bookings_count }}</strong> bookings</span><span><strong>{{ ucfirst($court->verification_status) }}</strong> evidence</span></div>
                    <div class="card-actions">
                        <a class="btn-primary" href="{{ route('owner.courts.manage', $court) }}">Manage operations</a>
                        <a class="btn-outline" href="{{ route('owner.courts.edit', $court) }}">Edit listing</a>
                    </div>
                </article>
            @empty
                <div class="panel-empty"><img src="{{ asset('images/kabacan-pickleplay-mark-v3.svg') }}" alt=""><h2>No court drafts yet.</h2><p>Start with verified location and contact information.</p></div>
            @endforelse
        </div>
    </div>
</x-app-layout>

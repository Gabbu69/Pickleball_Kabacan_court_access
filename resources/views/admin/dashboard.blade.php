<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="eyebrow">Platform administration</p><h1 class="dashboard-title">Kabacan PicklePlay control room</h1></div>
            <a class="btn-primary" href="{{ route('admin.courts.index') }}">Open verification queue</a>
        </div>
    </x-slot>

    <div class="metric-grid">
        @foreach ($counts as $label => $count)
            <div class="metric-card"><span>{{ $label }}</span><strong>{{ $count }}</strong><small>Current platform total</small></div>
        @endforeach
    </div>

    <div class="grid gap-6 mt-7 lg:grid-cols-3">
        <div class="metric-card metric-card-dark"><span>Net revenue</span><strong>₱{{ number_format($netRevenue / 100, 2) }}</strong><small>₱{{ number_format($grossRevenue / 100, 2) }} gross · ₱{{ number_format($refunds / 100, 2) }} refunded</small></div>
        <div class="metric-card"><span>Completed bookings</span><strong>{{ $completedBookings }}</strong><small>Eligible for verified reviews</small></div>
        <div class="metric-card"><span>Published reviews</span><strong>{{ $publishedReviews }}</strong><small>Visible player feedback</small></div>
    </div>

    <section class="panel mt-7">
        <div class="panel-heading"><div><p class="eyebrow">Accountability</p><h2>Latest audit activity</h2></div></div>
        <div class="audit-list">
            @forelse ($auditLogs as $log)
                <div><span>{{ $log->created_at->format('M j, g:i A') }}</span><strong>{{ str_replace('.', ' · ', $log->action) }}</strong><p>{{ $log->actor?->name ?? 'System' }}</p></div>
            @empty
                <p class="text-sm text-slate-500">Audit events will appear as administrators and owners manage the platform.</p>
            @endforelse
        </div>
    </section>
</x-app-layout>

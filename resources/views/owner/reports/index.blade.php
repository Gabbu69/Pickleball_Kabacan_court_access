<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Business intelligence</p><h1 class="dashboard-title">Booking and revenue reports</h1></div><a class="btn-outline" href="{{ route('owner.reports.export', request()->query()) }}">Export CSV</a></div>
    </x-slot>
    <form method="GET" class="filter-panel dashboard-filter">
        <div><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <div><label>Court</label><select name="court"><option value="">All courts</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(request('court') == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
        <button class="btn-primary">Update report</button>
    </form>
    <div class="metric-grid mt-7">
        <div class="metric-card"><span>Total bookings</span><strong>{{ $bookingCount }}</strong><small>Requests in selected dates</small></div>
        <div class="metric-card"><span>Completed</span><strong>{{ $completedCount }}</strong><small>Finished reservations</small></div>
        <div class="metric-card"><span>Cancelled/rejected</span><strong>{{ $cancelledCount }}</strong><small>Released inventory</small></div>
        <div class="metric-card"><span>Verified revenue</span><strong>₱{{ number_format($verifiedRevenue / 100, 2) }}</strong><small>Excludes unverified submissions</small></div>
    </div>
    <section class="panel mt-7">
        <p class="eyebrow">Court usage</p><h2 class="mt-2 text-2xl font-extrabold">{{ number_format($reservedMinutes / 60, 1) }} reserved hours</h2><p class="mt-2 text-sm text-slate-500">Confirmed and completed court time during {{ $from->format('M j') }}–{{ $to->format('M j, Y') }}.</p>
        <div class="usage-bar mt-6"><span style="width: {{ min(100, $bookingCount ? ($completedCount / $bookingCount) * 100 : 0) }}%"></span></div>
    </section>
</x-app-layout>

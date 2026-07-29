<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Business intelligence</p><h1 class="dashboard-title">Booking and revenue reports</h1></div><a class="btn-outline" href="{{ route('owner.reports.export', request()->query()) }}">Export CSV</a></div>
    </x-slot>
    <form method="GET" class="filter-panel dashboard-filter">
        <div><label for="report-from">From</label><input id="report-from" type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div><label for="report-to">To</label><input id="report-to" type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <div><label for="report-court">Court</label><select id="report-court" name="court"><option value="">All courts</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(request('court') == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
        <button class="btn-primary">Update report</button>
    </form>
    <div class="metric-grid mt-7">
        <div class="metric-card"><span>Total bookings</span><strong>{{ $bookingCount }}</strong><small>Requests in selected dates</small></div>
        <div class="metric-card"><span>Completion rate</span><strong>{{ number_format($completionRate, 1) }}%</strong><small>{{ $completedCount }} completed reservations</small></div>
        <div class="metric-card"><span>Cancellation rate</span><strong>{{ number_format($cancellationRate, 1) }}%</strong><small>{{ $cancelledCount }} cancelled, rejected, or expired</small></div>
        <div class="metric-card metric-card-dark"><span>Net revenue</span><strong>₱{{ number_format($netRevenue / 100, 2) }}</strong><small>₱{{ number_format($grossRevenue / 100, 2) }} gross · ₱{{ number_format($refunds / 100, 2) }} refunded · ₱{{ number_format($pendingPayments / 100, 2) }} pending</small></div>
    </div>
    <section class="panel mt-7">
        <p class="eyebrow">Court utilization</p><h2 class="mt-2 text-2xl font-extrabold">{{ number_format($utilizationPercent, 1) }}% utilized</h2><p class="mt-2 text-sm text-slate-500">{{ number_format($reservedMinutes / 60, 1) }} reserved hours out of {{ number_format($sellableMinutes / 60, 1) }} currently scheduled sellable hours during {{ $from->format('M j') }}–{{ $to->format('M j, Y') }}.</p>
        <div class="usage-bar mt-6" role="progressbar" aria-label="Court utilization" aria-valuenow="{{ $utilizationPercent }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $utilizationPercent }}%"></span></div>
        <p class="mt-4 text-sm text-slate-500">{{ $noShowCount }} no-show {{ Str::plural('booking', $noShowCount) }} tracked separately from cancellations.</p>
    </section>
</x-app-layout>

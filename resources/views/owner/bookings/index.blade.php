<x-app-layout>
    <x-slot name="header">
        <div><p class="eyebrow">Reservation desk</p><h1 class="dashboard-title">Bookings and payments</h1></div>
    </x-slot>

    <form class="filter-panel dashboard-filter" method="GET">
        <div><label>Status</label><select name="status"><option value="">All statuses</option>@foreach(['pending','confirmed','rejected','cancelled','completed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div><label>Court</label><select name="court"><option value="">All managed courts</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(($filters['court'] ?? '') == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
        <div><label>Date</label><input type="date" name="date" value="{{ $filters['date'] ?? '' }}"></div>
        <button class="btn-primary">Filter desk</button>
    </form>

    <div class="space-y-5 mt-6">
        @forelse ($bookings as $booking)
            <article class="panel booking-admin-card">
                <div class="booking-admin-main">
                    <div class="booking-date-block"><span>{{ $booking->starts_at->format('M') }}</span><strong>{{ $booking->starts_at->format('d') }}</strong></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><h2>{{ $booking->user->name }}</h2><span class="status status-{{ $booking->status->value }}">{{ ucfirst($booking->status->value) }}</span><span class="status status-{{ $booking->payment_status->value }}">Payment {{ $booking->payment_status->value }}</span></div>
                        <p>{{ $booking->court->name }} · {{ $booking->courtUnit->name }} · {{ $booking->starts_at->format('M j, Y g:i A') }}</p>
                        <small>{{ $booking->reference }} · {{ $booking->formatted_price }} · {{ $booking->user->phone }}</small>
                    </div>
                </div>
                <div class="booking-admin-actions">
                    @if ($booking->status->value === 'pending')
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><input class="form-input" name="notes" placeholder="Optional confirmation note"><button class="btn-primary">Confirm</button></form>
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input class="form-input" name="notes" placeholder="Reason" required><button class="btn-danger">Reject</button></form>
                    @elseif ($booking->status->value === 'confirmed')
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="btn-primary">Mark completed</button></form>
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><input class="form-input" name="notes" placeholder="Cancellation reason" required><button class="btn-danger">Cancel</button></form>
                    @endif
                </div>
                @foreach ($booking->payments as $payment)
                    <div class="payment-review-row">
                        <div><span class="status status-{{ $payment->status->value }}">{{ ucfirst($payment->status->value) }}</span><strong>₱{{ number_format($payment->amount_centavos / 100, 2) }} · {{ $payment->method_label }}</strong><small>{{ $payment->reference ?: 'Proof supplied without reference' }}</small></div>
                        <div class="flex flex-wrap gap-2">
                            @if($payment->proof_path)<a class="btn-quiet" href="{{ route('payments.proof', $payment) }}">View proof</a>@endif
                            @if($payment->status->value === 'submitted')
                                <form method="POST" action="{{ route('owner.payments.verify', $payment) }}">@csrf @method('PATCH')<button class="btn-primary">Verify</button></form>
                                <form method="POST" action="{{ route('owner.payments.reject', $payment) }}" class="flex gap-2">@csrf @method('PATCH')<input class="form-input" name="notes" placeholder="Reason" required><button class="btn-danger">Reject</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </article>
        @empty
            <div class="panel panel-empty"><h2>No reservations match these filters.</h2></div>
        @endforelse
    </div>
    <div class="mt-8">{{ $bookings->links() }}</div>
</x-app-layout>

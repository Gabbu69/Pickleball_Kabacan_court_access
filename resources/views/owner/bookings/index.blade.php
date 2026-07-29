<x-app-layout>
    <x-slot name="header">
        <div><p class="eyebrow">Reservation desk</p><h1 class="dashboard-title">Bookings and payments</h1></div>
    </x-slot>

    <form class="filter-panel dashboard-filter" method="GET">
        <div><label for="booking-status-filter">Status</label><select id="booking-status-filter" name="status"><option value="">All statuses</option>@foreach(['pending','confirmed','rejected','cancelled','completed','expired','no_show'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select></div>
        <div><label for="owner-bookings-court">Court</label><select id="owner-bookings-court" name="court"><option value="">All managed courts</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(($filters['court'] ?? '') == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
        <div><label for="owner-bookings-date">Date</label><input id="owner-bookings-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}"></div>
        <button class="btn-primary">Filter desk</button>
    </form>

    <div class="space-y-5 mt-6">
        @forelse ($bookings as $booking)
            <article class="panel booking-admin-card">
                <div class="booking-admin-main">
                    <div class="booking-date-block"><span>{{ $booking->starts_at->format('M') }}</span><strong>{{ $booking->starts_at->format('d') }}</strong></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><h2>{{ $booking->user->name }}</h2><span class="status status-{{ $booking->status->value }}">{{ str_replace('_', ' ', ucfirst($booking->status->value)) }}</span><span class="status status-{{ $booking->payment_status->value }}">Payment {{ str_replace('_', ' ', $booking->payment_status->value) }}</span>@if($booking->attendance?->status === 'checked_in')<span class="status status-checked_in">Checked in</span>@endif</div>
                        <p>{{ $booking->court->name }} · {{ $booking->courtUnit->name }} · {{ $booking->starts_at->format('M j, Y g:i A') }}</p>
                        <small>{{ $booking->reference }} · {{ $booking->formatted_price }} · {{ $booking->user->phone }}</small>
                    </div>
                </div>
                <div class="booking-admin-actions">
                    @if ($booking->status->value === 'pending')
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><input class="form-input" name="notes" placeholder="Optional confirmation note"><button class="btn-primary">Confirm</button></form>
                        <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input class="form-input" name="notes" placeholder="Reason" required><button class="btn-danger">Reject</button></form>
                    @elseif ($booking->status->value === 'confirmed')
                        @if ($booking->ends_at->isPast() && $booking->attendance?->status === 'checked_in')
                            <form method="POST" action="{{ route('owner.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="btn-primary">Mark completed</button></form>
                        @else
                            <a class="btn-outline" href="{{ route('owner.check-ins.index') }}">Open QR check-in</a>
                        @endif
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
                            @if(auth()->user()->isAdmin() && $payment->status->value === 'verified' && $payment->refundable_centavos > 0)
                                <form method="POST" action="{{ route('admin.payments.refunds.store', $payment) }}" class="refund-form">
                                    @csrf
                                    <label class="sr-only" for="refund-amount-{{ $payment->id }}">Refund amount</label>
                                    <input id="refund-amount-{{ $payment->id }}" class="form-input" type="number" name="amount" min="0.01" max="{{ number_format($payment->refundable_centavos / 100, 2, '.', '') }}" step="0.01" placeholder="Refund PHP" required>
                                    <label class="sr-only" for="refund-reason-{{ $payment->id }}">Refund reason</label>
                                    <input id="refund-reason-{{ $payment->id }}" class="form-input" name="reason" placeholder="Refund reason" required>
                                    <button class="btn-outline">Record refund</button>
                                </form>
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

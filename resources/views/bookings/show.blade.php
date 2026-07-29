<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('bookings.index') }}" class="back-link">← My bookings</a>
                <p class="eyebrow mt-4">Reservation {{ $booking->reference }}</p>
                <h1 class="dashboard-title">{{ $booking->court->name }}</h1>
            </div>
            <span class="status status-lg status-{{ $booking->status->value }}">{{ str_replace('_', ' ', ucfirst($booking->status->value)) }}</span>
        </div>
    </x-slot>

    @if (session('success') && str_contains(session('success'), 'created'))
        <div class="success-burst" aria-hidden="true">
            @for ($i = 0; $i < 12; $i++)<span style="--i:{{ $i }}"></span>@endfor
        </div>
    @endif

    <div class="booking-detail-grid">
        <div>
            <section class="panel">
                @php
                    $steps = ['pending' => 1, 'confirmed' => 2, 'completed' => 4];
                    $progress = $steps[$booking->status->value] ?? ($booking->status->value === 'cancelled' || $booking->status->value === 'rejected' ? 1 : 1);
                    if ($booking->attendance?->status === 'checked_in' && $booking->status->value === 'confirmed') $progress = 3;
                @endphp
                <div class="booking-progress">
                    @foreach ([['Request sent','Owner review'],['Confirmed','Court reserved'],['Checked in','QR attendance'],['Completed','Ready to review']] as $index => [$label,$copy])
                        <div class="{{ $progress >= $index + 1 ? 'is-active' : '' }}">
                            <span>{{ $progress > $index + 1 ? '✓' : $index + 1 }}</span>
                            <strong>{{ $label }}</strong>
                            <small>{{ $copy }}</small>
                        </div>
                    @endforeach
                </div>

                <div class="booking-facts mt-8">
                    <div><span>Date</span><strong>{{ $booking->starts_at->format('l, F j, Y') }}</strong></div>
                    <div><span>Time</span><strong>{{ $booking->starts_at->format('g:i A') }} – {{ $booking->ends_at->format('g:i A') }}</strong></div>
                    <div><span>Playable court</span><strong>{{ $booking->courtUnit->name }}</strong></div>
                    <div><span>Rate snapshot</span><strong>{{ $booking->formatted_price }}</strong></div>
                    <div><span>Payment</span><strong>{{ ucfirst($booking->payment_status->value) }}</strong></div>
                    <div><span>Paid / balance</span><strong>₱{{ number_format($booking->net_paid_centavos / 100, 2) }} / ₱{{ number_format($booking->outstanding_centavos / 100, 2) }}</strong></div>
                    <div><span>Cancellation cutoff</span><strong>{{ $booking->court->cancellation_cutoff_hours }} hours before play</strong></div>
                </div>

                @if ($booking->player_notes)
                    <div class="booking-note"><span>Your note</span><p>{{ $booking->player_notes }}</p></div>
                @endif
                @if ($booking->owner_notes)
                    <div class="booking-note booking-note-owner"><span>Venue response</span><p>{{ $booking->owner_notes }}</p></div>
                @endif
            </section>

            @if ($booking->status->value === 'confirmed')
                <section class="panel qr-pass-cta mt-6">
                    <div>
                        <p class="eyebrow">Match-day access</p>
                        <h2>{{ $booking->attendance?->status === 'checked_in' ? 'You are checked in.' : 'Your QR court pass is ready.' }}</h2>
                        <p>{{ $booking->attendance?->status === 'checked_in' ? 'Attendance has been verified by the venue.' : 'Open the pass when you arrive and let the venue manager scan it.' }}</p>
                    </div>
                    <a class="btn-primary" href="{{ route('bookings.pass', $booking) }}">
                        {{ $booking->attendance?->status === 'checked_in' ? 'View check-in' : 'Open QR pass' }}
                    </a>
                </section>
            @endif

            @if ($booking->status->value === 'completed' && $booking->attendance?->status === 'checked_in' && ! $booking->review)
                <section class="panel mt-6">
                    <div class="panel-heading"><div><p class="eyebrow">Verified review</p><h2>How was the court?</h2></div></div>
                    <form method="POST" action="{{ route('reviews.store', $booking) }}" class="mt-5 space-y-4">
                        @csrf
                        <div><label for="booking-review-rating">Rating</label><select id="booking-review-rating" class="form-input" name="rating" required><option value="">Choose</option>@for($i=5;$i>=1;$i--)<option value="{{ $i }}">{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>@endfor</select></div>
                        <div><label for="booking-review-body">Review</label><textarea id="booking-review-body" class="form-input min-h-28" name="body" required minlength="10" maxlength="1500"></textarea></div>
                        <button class="btn-primary">Submit verified review</button>
                    </form>
                </section>
            @endif
        </div>

        <aside>
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Payment tracking</p><h2>{{ str_replace('_', ' ', ucfirst($booking->payment_status->value)) }}</h2></div></div>
                <div class="payment-balance">
                    <div><span>Verified paid</span><strong>₱{{ number_format($booking->verified_paid_centavos / 100, 2) }}</strong></div>
                    <div><span>Refunded</span><strong>₱{{ number_format($booking->refunded_centavos / 100, 2) }}</strong></div>
                    <div><span>Outstanding</span><strong>₱{{ number_format($booking->outstanding_centavos / 100, 2) }}</strong></div>
                </div>
                <p class="text-sm leading-6 text-slate-500">
                    {{ match($booking->court->payment_policy) {
                        'pay_on_site' => 'This venue accepts payment at the court. You may still submit a reference if requested.',
                        'proof_required' => 'The owner must verify a submitted payment before confirming the reservation.',
                        default => 'Choose an available manual method or pay at the venue.',
                    } }}
                </p>

                @if (in_array($booking->status->value, ['pending','confirmed']) && $booking->court->payment_policy !== 'pay_on_site' && $booking->outstanding_centavos > 0 && ! $booking->payments->contains(fn($payment) => $payment->status->value === 'submitted'))
                    <form method="POST" action="{{ route('payments.store', $booking) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="booking-payment-method">Payment method</label>
                            <select id="booking-payment-method" class="form-input" name="court_payment_method_id">
                                @foreach ($booking->court->paymentMethods->where('is_active', true) as $method)
                                    <option value="{{ $method->id }}">{{ $method->label }}{{ $method->account_reference ? ' · '.$method->account_reference : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="booking-payment-amount">Amount sent</label><input id="booking-payment-amount" class="form-input" type="number" min="0.01" max="{{ number_format($booking->outstanding_centavos / 100, 2, '.', '') }}" step="0.01" name="amount" value="{{ number_format($booking->outstanding_centavos / 100, 2, '.', '') }}" required></div>
                        <div><label for="booking-payment-reference">Transaction reference</label><input id="booking-payment-reference" class="form-input" name="reference" maxlength="120"></div>
                        <div><label for="booking-payment-proof">Proof <span>(JPG, PNG, WebP, or PDF)</span></label><input id="booking-payment-proof" class="form-input" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
                        <button class="btn-primary w-full justify-center">Submit payment details</button>
                    </form>
                @endif

                @if ($booking->payments->isNotEmpty())
                    <div class="payment-history">
                        @foreach ($booking->payments as $payment)
                            <div>
                                <span class="status status-{{ $payment->status->value }}">{{ ucfirst($payment->status->value) }}</span>
                                <strong>₱{{ number_format($payment->amount_centavos / 100, 2) }} · {{ $payment->method_label }}</strong>
                                <small>{{ $payment->submitted_at->format('M j, Y g:i A') }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</small>
                                @if ($payment->proof_path)<a href="{{ route('payments.proof', $payment) }}">View submitted proof</a>@endif
                                @if ($payment->reviewer_notes)<p>{{ $payment->reviewer_notes }}</p>@endif
                                @if ($payment->refunds->isNotEmpty())
                                    <p>{{ $payment->refunds->count() }} refund record(s) · ₱{{ number_format($payment->refunds->sum('amount_centavos') / 100, 2) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($booking->canBeCancelledBy(auth()->user()))
                <section class="panel panel-danger mt-6">
                    <p class="eyebrow">Need to release the slot?</p>
                    <h2>Cancel reservation</h2>
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="mt-4 space-y-3">
                        @csrf @method('PATCH')
                        <textarea class="form-input min-h-24" name="reason" required placeholder="Reason for cancellation"></textarea>
                        <button class="btn-danger">Cancel and release slot</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>

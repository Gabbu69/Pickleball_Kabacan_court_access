<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('bookings.show', $booking) }}" class="back-link">← Reservation details</a>
                <p class="eyebrow mt-4">Match-day access</p>
                <h1 class="dashboard-title">Your court pass</h1>
            </div>
            <span class="status status-lg status-{{ $booking->attendance?->status ?? 'issued' }}">
                {{ $booking->attendance?->status === 'checked_in' ? 'Checked in' : 'Ready to scan' }}
            </span>
        </div>
    </x-slot>

    <div class="pass-layout">
        <section class="court-pass">
            <div class="court-pass-brand">
                <x-application-logo class="h-12 w-12" />
                <span><small>Kabacan</small><strong>PicklePlay</strong></span>
            </div>

            @if ($qrPayload)
                <div class="court-pass-qr">
                    <canvas data-booking-qr data-payload="{{ $qrPayload }}" aria-label="QR check-in pass"></canvas>
                </div>
                <p class="court-pass-help">Let the venue owner scan this code. It contains no name, phone number, or payment details.</p>
            @else
                <div class="court-pass-complete" role="status">
                    <span>✓</span>
                    <h2>Check-in recorded</h2>
                    <p>{{ $booking->attendance->checked_in_at?->format('M j, Y g:i A') }}</p>
                </div>
            @endif

            <div class="court-pass-reference">
                <span>Reservation</span>
                <strong>{{ $booking->reference }}</strong>
            </div>
        </section>

        <aside class="panel pass-details">
            <p class="eyebrow">Verified reservation</p>
            <h2>{{ $booking->court->name }}</h2>
            <dl>
                <div><dt>Playable court</dt><dd>{{ $booking->courtUnit->name }}</dd></div>
                <div><dt>Date</dt><dd>{{ $booking->starts_at->format('l, F j, Y') }}</dd></div>
                <div><dt>Time</dt><dd>{{ $booking->starts_at->format('g:i A') }}–{{ $booking->ends_at->format('g:i A') }}</dd></div>
                <div><dt>Payment</dt><dd>{{ str_replace('_', ' ', ucfirst($booking->payment_status->value)) }}</dd></div>
            </dl>
            <div class="pass-window">
                <strong>Scan window</strong>
                <p>{{ $booking->starts_at->copy()->subMinutes(30)->format('g:i A') }}–{{ $booking->starts_at->copy()->addMinutes(30)->format('g:i A') }}</p>
                <small>Thirty minutes before through thirty minutes after your scheduled start.</small>
            </div>
        </aside>
    </div>
</x-app-layout>

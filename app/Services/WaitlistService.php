<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    public function offerNextForBooking(Booking $booking): ?WaitlistOffer
    {
        $offer = DB::transaction(function () use ($booking) {
            $this->expireOffersForSlot($booking->court_unit_id, $booking->starts_at);

            if (WaitlistOffer::query()
                ->where('status', 'active')
                ->whereHas('entry', fn ($query) => $query
                    ->where('court_unit_id', $booking->court_unit_id)
                    ->where('starts_at', $booking->starts_at))
                ->exists()) {
                return null;
            }

            $entry = WaitlistEntry::query()
                ->where('court_unit_id', $booking->court_unit_id)
                ->where('starts_at', $booking->starts_at)
                ->where('status', 'waiting')
                ->oldest()
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return null;
            }

            $offer = $entry->offers()->create([
                'public_id' => (string) Str::uuid(),
                'status' => 'active',
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(15),
            ]);

            $entry->update(['status' => 'offered', 'notified_at' => now()]);
            AuditService::record('waitlist.offer_created', $offer);

            return $offer->load('entry.user', 'entry.court');
        });

        if ($offer) {
            try {
                $offer->entry->user->notify(new PlatformNotification(
                    'Your waitlist slot is ready',
                    "You have 15 minutes to claim {$offer->entry->court->name} on {$offer->entry->starts_at->format('M j, g:i A')}.",
                    '/bookings#waitlist',
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $offer;
    }

    public function accept(WaitlistOffer $offer, User $user, BookingService $bookings): Booking
    {
        $offer->load('entry.court');
        abort_unless($offer->entry->user_id === $user->id, 403);

        if (! $offer->isActive()) {
            $this->expire($offer);
            throw ValidationException::withMessages(['waitlist' => 'This priority offer has expired.']);
        }

        $entry = $offer->entry;
        $booking = $bookings->create(
            $user,
            $entry->court,
            $entry->court_unit_id,
            $entry->starts_at->timezone(AvailabilityService::TIMEZONE)->toDateString(),
            $entry->starts_at->timezone(AvailabilityService::TIMEZONE)->format('H:i'),
            'Created from waitlist priority offer.',
        );

        DB::transaction(function () use ($offer, $entry) {
            $offer->update(['status' => 'accepted', 'accepted_at' => now()]);
            $entry->update(['status' => 'accepted']);
        });
        AuditService::record('waitlist.offer_accepted', $offer, ['booking_reference' => $booking->reference]);

        return $booking;
    }

    public function expire(WaitlistOffer $offer): void
    {
        DB::transaction(function () use ($offer) {
            $offer = WaitlistOffer::query()->with('entry')->lockForUpdate()->findOrFail($offer->id);

            if ($offer->status !== 'active') {
                return;
            }

            $offer->update(['status' => 'expired', 'expired_at' => now()]);
            $offer->entry->update(['status' => 'expired']);
            AuditService::record('waitlist.offer_expired', $offer);
        });
    }

    public function expireDueOffers(): int
    {
        $offers = WaitlistOffer::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('entry')
            ->get();

        foreach ($offers as $offer) {
            $this->expire($offer);

            $entry = $offer->entry;
            $booking = Booking::query()
                ->where('court_unit_id', $entry->court_unit_id)
                ->where('starts_at', $entry->starts_at)
                ->whereIn('status', ['cancelled', 'rejected', 'expired'])
                ->latest()
                ->first();

            if ($booking) {
                $this->offerNextForBooking($booking);
            }
        }

        return $offers->count();
    }

    private function expireOffersForSlot(int $unitId, $startsAt): void
    {
        WaitlistOffer::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->whereHas('entry', fn ($query) => $query
                ->where('court_unit_id', $unitId)
                ->where('starts_at', $startsAt))
            ->with('entry')
            ->get()
            ->each(fn (WaitlistOffer $offer) => $this->expire($offer));
    }
}

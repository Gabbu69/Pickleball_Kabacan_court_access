<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\PlatformNotification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    public function create(User $user, Court $court, int $unitId, string $date, string $startTime, ?string $notes = null): Booking
    {
        $slot = $this->availability->findAvailableSlot($court, $unitId, $date, $startTime);

        if (! $slot) {
            throw ValidationException::withMessages(['slot' => 'That time slot is no longer available. Please choose another one.']);
        }

        $lockKey = 'booking-slot:'.hash('sha256', $unitId.'|'.$slot['start_at']);

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($user, $court, $unitId, $date, $startTime, $notes) {
                $freshSlot = $this->availability->findAvailableSlot($court->fresh(), $unitId, $date, $startTime);

                if (! $freshSlot) {
                    throw ValidationException::withMessages(['slot' => 'Another player just reserved that slot. Please choose another one.']);
                }

                $booking = DB::transaction(function () use ($user, $court, $freshSlot, $notes) {
                    return Booking::create([
                        'reference' => $this->reference(),
                        'user_id' => $user->id,
                        'court_id' => $court->id,
                        'court_unit_id' => $freshSlot['unit_id'],
                        'court_schedule_rule_id' => $freshSlot['rule_id'],
                        'starts_at' => $freshSlot['start_at'],
                        'ends_at' => $freshSlot['end_at'],
                        'status' => BookingStatus::Pending,
                        'payment_status' => PaymentStatus::Unpaid,
                        'price_centavos' => $freshSlot['price_centavos'],
                        'currency' => 'PHP',
                        'player_notes' => $notes,
                    ]);
                });

                $booking->load(['court.managers', 'courtUnit', 'user']);
                $recipients = $booking->court->managers
                    ->merge(User::query()->where('role', 'admin')->where('status', 'active')->get())
                    ->unique('id');

                Notification::send($recipients, new PlatformNotification(
                    'New reservation '.$booking->reference,
                    "{$user->name} requested {$booking->courtUnit->name} on {$booking->starts_at->format('M j, Y g:i A')}.",
                    '/owner/bookings',
                ));

                $user->notify(new PlatformNotification(
                    'Reservation received',
                    "Your request {$booking->reference} is awaiting court-owner approval.",
                    '/bookings/'.$booking->reference,
                ));

                AuditService::record('booking.created', $booking, ['reference' => $booking->reference]);

                return $booking;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'slot' => 'That slot is being reserved by another player. Please refresh and try again.',
            ]);
        }
    }

    public function cancelByPlayer(Booking $booking, User $user, string $reason): Booking
    {
        if (! $booking->canBeCancelledBy($user)) {
            throw ValidationException::withMessages(['cancellation' => 'This reservation is outside the court cancellation window.']);
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        $this->notifyWaitlist($booking);
        $this->notifyManagers($booking, 'Reservation cancelled', "{$booking->reference} was cancelled by {$user->name}.");
        AuditService::record('booking.cancelled_by_player', $booking, ['reason' => $reason]);

        return $booking;
    }

    public function transition(Booking $booking, User $actor, BookingStatus $status, ?string $notes = null): Booking
    {
        $allowed = match ($status) {
            BookingStatus::Confirmed, BookingStatus::Rejected => $booking->status === BookingStatus::Pending,
            BookingStatus::Cancelled => in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true),
            BookingStatus::Completed => $booking->status === BookingStatus::Confirmed,
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['status' => 'That reservation status change is not allowed.']);
        }

        if ($status === BookingStatus::Confirmed
            && $booking->court->payment_policy === 'proof_required'
            && $booking->payment_status !== PaymentStatus::Verified) {
            throw ValidationException::withMessages(['status' => 'Verify the required payment before confirming this reservation.']);
        }

        $data = ['status' => $status, 'owner_notes' => $notes];

        if ($status === BookingStatus::Confirmed) {
            $data += ['approved_by' => $actor->id, 'approved_at' => now()];
        }

        if ($status === BookingStatus::Completed) {
            $data['completed_at'] = now();
        }

        if ($status === BookingStatus::Cancelled) {
            $data += ['cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $notes];
        }

        $booking->update($data);

        if (in_array($status, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            $this->notifyWaitlist($booking);
        }

        $booking->user->notify(new PlatformNotification(
            'Reservation '.str_replace('_', ' ', $status->value),
            "{$booking->reference} for {$booking->court->name} is now {$status->value}.",
            '/bookings/'.$booking->reference,
        ));

        AuditService::record('booking.'.$status->value, $booking, ['notes' => $notes]);

        return $booking;
    }

    private function notifyWaitlist(Booking $booking): void
    {
        $entry = WaitlistEntry::query()
            ->where('court_unit_id', $booking->court_unit_id)
            ->where('starts_at', $booking->starts_at)
            ->where('status', 'waiting')
            ->oldest()
            ->first();

        if (! $entry) {
            return;
        }

        $entry->update(['status' => 'notified', 'notified_at' => now()]);
        $entry->user->notify(new PlatformNotification(
            'A court slot reopened',
            "{$booking->court->name} has reopened the {$booking->starts_at->format('M j, g:i A')} slot.",
            '/courts/'.$booking->court->slug,
        ));
    }

    private function notifyManagers(Booking $booking, string $title, string $message): void
    {
        Notification::send($booking->court->managers, new PlatformNotification($title, $message, '/owner/bookings'));
    }

    private function reference(): string
    {
        do {
            $reference = 'KPP-'.Str::upper(Str::random(8));
        } while (Booking::where('reference', $reference)->exists());

        return $reference;
    }
}

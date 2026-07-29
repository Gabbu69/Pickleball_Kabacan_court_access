<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function issuePass(Booking $booking): string
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw ValidationException::withMessages(['attendance' => 'A QR pass is available after the reservation is confirmed.']);
        }

        $token = Str::random(64);
        $booking->attendance()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'token_hash' => hash('sha256', $token),
                'status' => 'issued',
                'checked_in_by' => null,
                'checked_in_at' => null,
                'revoked_at' => null,
            ],
        );

        return 'KPP-CHECKIN:'.$token;
    }

    public function checkIn(string $rawToken, User $actor, ?string $ip = null, ?string $userAgent = null): BookingAttendance
    {
        $token = Str::after(trim($rawToken), 'KPP-CHECKIN:');
        $attendance = BookingAttendance::query()
            ->with('booking.court')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages(['token' => 'This QR pass is invalid or has been replaced.']);
        }

        $booking = $attendance->booking;
        abort_unless($booking->court->isManagedBy($actor), 403);

        if ($booking->status !== BookingStatus::Confirmed) {
            throw ValidationException::withMessages(['token' => 'This reservation is not confirmed for check-in.']);
        }

        $now = now();
        $windowStart = $booking->starts_at->copy()->subMinutes(30);
        $windowEnd = $booking->starts_at->copy()->addMinutes(30);

        if (! $now->betweenIncluded($windowStart, $windowEnd)) {
            throw ValidationException::withMessages([
                'token' => 'Check-in opens 30 minutes before and closes 30 minutes after the scheduled start.',
            ]);
        }

        return DB::transaction(function () use ($attendance, $actor, $ip, $userAgent) {
            $attendance = BookingAttendance::query()->lockForUpdate()->findOrFail($attendance->id);

            if ($attendance->status === 'checked_in') {
                return $attendance;
            }

            $attendance->update([
                'status' => 'checked_in',
                'checked_in_by' => $actor->id,
                'checked_in_at' => now(),
                'scan_ip' => $ip,
                'scan_user_agent' => Str::limit((string) $userAgent, 1000, ''),
            ]);
            AuditService::record('booking.checked_in', $attendance, ['booking_id' => $attendance->booking_id]);

            return $attendance;
        });
    }
}

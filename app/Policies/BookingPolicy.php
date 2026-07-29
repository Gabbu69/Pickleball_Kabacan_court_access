<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            || $user->isAdmin()
            || $booking->court->isManagedBy($user);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function manage(User $user, Booking $booking): bool
    {
        return $booking->court->isManagedBy($user);
    }
}

<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function occupiesSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlotClaim extends Model
{
    protected $fillable = ['booking_id', 'court_unit_id', 'slot_starts_at', 'slot_ends_at'];

    protected function casts(): array
    {
        return [
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function courtUnit(): BelongsTo
    {
        return $this->belongsTo(CourtUnit::class);
    }
}

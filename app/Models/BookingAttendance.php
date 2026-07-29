<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAttendance extends Model
{
    protected $fillable = [
        'booking_id',
        'token_hash',
        'status',
        'checked_in_by',
        'checked_in_at',
        'revoked_at',
        'scan_ip',
        'scan_user_agent',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}

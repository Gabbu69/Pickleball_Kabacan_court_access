<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistOffer extends Model
{
    protected $fillable = [
        'public_id',
        'waitlist_entry_id',
        'status',
        'offered_at',
        'expires_at',
        'accepted_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(WaitlistEntry::class, 'waitlist_entry_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}

<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'court_id',
        'court_unit_id',
        'court_schedule_rule_id',
        'starts_at',
        'ends_at',
        'status',
        'payment_status',
        'price_centavos',
        'currency',
        'player_notes',
        'owner_notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'approved_by',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function courtUnit(): BelongsTo
    {
        return $this->belongsTo(CourtUnit::class);
    }

    public function scheduleRule(): BelongsTo
    {
        return $this->belongsTo(CourtScheduleRule::class, 'court_schedule_rule_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function canBeCancelledBy(User $user): bool
    {
        if ($this->user_id !== $user->id || ! in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            return false;
        }

        return now()->lt($this->starts_at->copy()->subHours($this->court->cancellation_cutoff_hours));
    }

    public function getFormattedPriceAttribute(): string
    {
        return '₱'.number_format($this->price_centavos / 100, 2);
    }
}

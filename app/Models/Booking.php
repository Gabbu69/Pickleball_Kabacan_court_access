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
        'expires_at',
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
        'no_show_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'no_show_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $status) {
            $status->where('status', BookingStatus::Confirmed->value)
                ->orWhere(function (Builder $pending) {
                    $pending->where('status', BookingStatus::Pending->value)
                        ->where(function (Builder $expiry) {
                            $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        });
                });
        });
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

    public function slotClaims(): HasMany
    {
        return $this->hasMany(BookingSlotClaim::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(BookingAttendance::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
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

    public function getVerifiedPaidCentavosAttribute(): int
    {
        return (int) $this->payments()
            ->where('status', PaymentStatus::Verified->value)
            ->sum('amount_centavos');
    }

    public function getRefundedCentavosAttribute(): int
    {
        return (int) $this->refunds()->sum('amount_centavos');
    }

    public function getNetPaidCentavosAttribute(): int
    {
        return max(0, $this->verified_paid_centavos - $this->refunded_centavos);
    }

    public function getOutstandingCentavosAttribute(): int
    {
        return max(0, $this->price_centavos - $this->net_paid_centavos);
    }
}

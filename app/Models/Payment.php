<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'court_payment_method_id',
        'method_label',
        'amount_centavos',
        'reference',
        'proof_path',
        'proof_disk',
        'proof_url',
        'proof_mime',
        'proof_bytes',
        'status',
        'submitted_at',
        'verified_by',
        'verified_at',
        'reviewer_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function getRefundableCentavosAttribute(): int
    {
        return max(0, $this->amount_centavos - (int) $this->refunds()->sum('amount_centavos'));
    }
}

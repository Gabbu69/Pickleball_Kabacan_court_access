<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

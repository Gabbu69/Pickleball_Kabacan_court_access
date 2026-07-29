<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtVerificationClaim extends Model
{
    public const REQUIRED_FIELDS = [
        'identity' => 'Court identity',
        'address' => 'Complete Kabacan address',
        'map_location' => 'Map location',
        'court_type' => 'Court classification',
        'operating_hours' => 'Operating hours',
        'rental_rate' => 'Rental rate',
        'schedule' => 'Bookable schedule',
        'contact' => 'Contact details',
        'photos' => 'Actual court photos',
        'amenities' => 'Available amenities',
    ];

    protected $fillable = [
        'court_id',
        'court_verification_id',
        'field_key',
        'status',
        'value_hash',
        'verified_by',
        'verified_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(CourtVerification::class, 'court_verification_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtScheduleRule extends Model
{
    protected $fillable = [
        'court_unit_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'slot_minutes',
        'price_centavos',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function courtUnit(): BelongsTo
    {
        return $this->belongsTo(CourtUnit::class);
    }
}

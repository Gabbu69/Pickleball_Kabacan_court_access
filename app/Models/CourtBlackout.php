<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtBlackout extends Model
{
    protected $fillable = ['court_id', 'court_unit_id', 'starts_at', 'ends_at', 'reason', 'is_public', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function courtUnit(): BelongsTo
    {
        return $this->belongsTo(CourtUnit::class);
    }
}

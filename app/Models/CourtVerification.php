<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtVerification extends Model
{
    protected $fillable = [
        'court_id',
        'type',
        'source_url',
        'notes',
        'evidence_path',
        'submitted_by',
        'verified_by',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

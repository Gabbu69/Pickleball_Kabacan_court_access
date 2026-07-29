<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtVerification extends Model
{
    protected $fillable = [
        'court_id',
        'type',
        'source_url',
        'notes',
        'evidence_path',
        'evidence_disk',
        'evidence_mime',
        'evidence_bytes',
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

    public function claims(): HasMany
    {
        return $this->hasMany(CourtVerificationClaim::class);
    }
}

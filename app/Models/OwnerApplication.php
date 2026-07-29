<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'court_id',
        'proposed_court_name',
        'message',
        'evidence_path',
        'evidence_disk',
        'evidence_url',
        'evidence_mime',
        'evidence_bytes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

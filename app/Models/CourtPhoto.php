<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtPhoto extends Model
{
    protected $fillable = ['court_id', 'path', 'alt_text', 'caption', 'is_primary', 'rights_confirmed_at', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'rights_confirmed_at' => 'datetime'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}

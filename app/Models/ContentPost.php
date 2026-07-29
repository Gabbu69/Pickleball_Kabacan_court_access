<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPost extends Model
{
    protected $fillable = [
        'court_id',
        'created_by',
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'starts_at',
        'ends_at',
        'image_path',
        'image_disk',
        'image_url',
        'image_mime',
        'image_bytes',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNotNull('published_at');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function getPublicImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return $this->image_url ?: asset($this->image_path);
    }
}

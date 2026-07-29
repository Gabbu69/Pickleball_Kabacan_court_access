<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtPhoto extends Model
{
    protected $fillable = [
        'court_id',
        'path',
        'storage_disk',
        'storage_url',
        'mime_type',
        'size_bytes',
        'alt_text',
        'caption',
        'is_primary',
        'rights_confirmed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'rights_confirmed_at' => 'datetime'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return $this->storage_url ?: asset($this->path);
    }

    public function usesVercelImageOptimization(): bool
    {
        return $this->storage_disk === 'vercel_blob_public'
            && str_contains((string) $this->storage_url, '.public.blob.vercel-storage.com/');
    }

    public function optimizedUrl(int $width, int $quality = 75): string
    {
        if (! $this->usesVercelImageOptimization()) {
            return $this->public_url;
        }

        return '/_vercel/image?'.http_build_query([
            'url' => $this->public_url,
            'w' => $width,
            'q' => $quality,
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    public function responsiveSrcset(array $widths = [320, 640, 960, 1280]): ?string
    {
        if (! $this->usesVercelImageOptimization()) {
            return null;
        }

        return collect($widths)
            ->map(fn (int $width) => $this->optimizedUrl($width).' '.$width.'w')
            ->implode(', ');
    }
}

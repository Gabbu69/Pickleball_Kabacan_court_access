<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'user_id',
        'court_id',
        'court_unit_id',
        'court_schedule_rule_id',
        'starts_at',
        'ends_at',
        'status',
        'notified_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(WaitlistOffer::class);
    }

    public function latestOffer(): HasOne
    {
        return $this->hasOne(WaitlistOffer::class)->latestOfMany();
    }
}

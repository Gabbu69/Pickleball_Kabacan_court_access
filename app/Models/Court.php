<?php

namespace App\Models;

use App\Enums\CourtStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'address_line',
        'barangay',
        'municipality',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'google_maps_url',
        'environment',
        'venue_type',
        'phone',
        'email',
        'facebook_url',
        'verification_status',
        'status',
        'payment_policy',
        'cancellation_cutoff_hours',
        'is_featured',
        'verified_by',
        'verified_at',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourtStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_featured' => 'boolean',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', CourtStatus::Published->value)
            ->where('verification_status', 'verified')
            ->whereNotNull('published_at')
            ->whereNotNull('address_line')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('environment')
            ->whereRaw('lower(municipality) = ?', ['kabacan'])
            ->whereRaw('lower(province) = ?', ['cotabato'])
            ->where(fn (Builder $contact) => $contact
                ->whereNotNull('phone')
                ->orWhereNotNull('email')
                ->orWhereNotNull('facebook_url'))
            ->whereHas('photos', fn (Builder $photos) => $photos->whereNotNull('rights_confirmed_at'))
            ->whereHas('activeUnits', fn (Builder $units) => $units->whereHas(
                'scheduleRules',
                fn (Builder $rules) => $rules->where('court_schedule_rules.is_active', true),
            ))
            ->whereHas('operatingHours', fn (Builder $hours) => $hours
                ->where('court_operating_hours.is_closed', false)
                ->whereNotNull('court_operating_hours.opens_at')
                ->whereNotNull('court_operating_hours.closes_at'))
            ->whereHas('verifications', fn (Builder $verifications) => $verifications->where('court_verifications.status', 'accepted'))
            ->where(fn (Builder $payment) => $payment
                ->where('payment_policy', 'pay_on_site')
                ->orWhereHas('paymentMethods', fn (Builder $methods) => $methods->where('court_payment_methods.is_active', true)));
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function units(): HasMany
    {
        return $this->hasMany(CourtUnit::class)->orderBy('sort_order');
    }

    public function activeUnits(): HasMany
    {
        return $this->units()->where('is_active', true);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CourtPhoto::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(CourtPhoto::class)->where('is_primary', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(CourtOperatingHour::class)->orderBy('day_of_week');
    }

    public function scheduleRules(): HasManyThrough
    {
        return $this->hasManyThrough(CourtScheduleRule::class, CourtUnit::class);
    }

    public function blackouts(): HasMany
    {
        return $this->hasMany(CourtBlackout::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(CourtPaymentMethod::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(CourtVerification::class)->latest();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function publishedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'published')->latest();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isManagedBy(User $user): bool
    {
        return $user->isAdmin() || $this->managers()->whereKey($user->id)->exists();
    }

    public function isPubliclyDiscoverable(): bool
    {
        return $this->status === CourtStatus::Published
            && $this->verification_status === 'verified'
            && $this->published_at !== null
            && $this->publishabilityErrors() === [];
    }

    public function publishabilityErrors(): array
    {
        $errors = [];

        foreach ([
            'address_line' => 'Complete address',
            'latitude' => 'Map latitude',
            'longitude' => 'Map longitude',
            'environment' => 'Indoor or outdoor classification',
        ] as $field => $label) {
            if (blank($this->{$field})) {
                $errors[] = $label;
            }
        }

        if (strtolower($this->municipality) !== 'kabacan' || strtolower($this->province) !== 'cotabato') {
            $errors[] = 'Kabacan, Cotabato location';
        }

        if (! $this->phone && ! $this->email && ! $this->facebook_url) {
            $errors[] = 'Public contact detail';
        }

        if (! $this->photos()->whereNotNull('rights_confirmed_at')->exists()) {
            $errors[] = 'Rights-confirmed actual court photo';
        }

        if (! $this->activeUnits()->exists()) {
            $errors[] = 'Active playable court';
        }

        if (! $this->operatingHours()
            ->where('is_closed', false)
            ->whereNotNull('opens_at')
            ->whereNotNull('closes_at')
            ->exists()) {
            $errors[] = 'Operating hours';
        }

        if (! $this->scheduleRules()->where('court_schedule_rules.is_active', true)->exists()) {
            $errors[] = 'Active schedule and rental rate';
        }

        if (! $this->verifications()->where('status', 'accepted')->exists()) {
            $errors[] = 'Accepted verification evidence';
        }

        if ($this->payment_policy !== 'pay_on_site' && ! $this->paymentMethods()->where('is_active', true)->exists()) {
            $errors[] = 'Active payment method';
        }

        return $errors;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line,
            $this->barangay,
            $this->municipality,
            $this->province,
            $this->postal_code,
        ])->filter()->unique()->implode(', ');
    }
}

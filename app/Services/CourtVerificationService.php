<?php

namespace App\Services;

use App\Enums\CourtStatus;
use App\Models\Court;
use App\Models\CourtVerification;
use App\Models\CourtVerificationClaim;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourtVerificationService
{
    /**
     * @param  array<int, string>  $fields
     */
    public function attachClaims(CourtVerification $verification, array $fields): void
    {
        foreach (array_unique($fields) as $field) {
            $verification->claims()->updateOrCreate(
                ['field_key' => $field],
                [
                    'court_id' => $verification->court_id,
                    'status' => 'pending',
                    'value_hash' => $this->valueHash($verification->court, $field),
                ],
            );
        }
    }

    public function accept(CourtVerification $verification, User $reviewer, ?string $notes = null): CourtVerification
    {
        return DB::transaction(function () use ($verification, $reviewer, $notes) {
            $verification = CourtVerification::query()->lockForUpdate()->findOrFail($verification->id);
            $verification->load('court');

            if ($verification->claims()->doesntExist()) {
                throw new \DomainException('Evidence must identify at least one fact before it can be accepted.');
            }

            $verification->claims()->where('status', 'pending')->get()->each(function (CourtVerificationClaim $claim) use ($reviewer, $verification) {
                $claim->update([
                    'status' => 'accepted',
                    'value_hash' => $this->valueHash($verification->court, $claim->field_key),
                    'verified_by' => $reviewer->id,
                    'verified_at' => now(),
                    'invalidated_at' => null,
                    'invalidation_reason' => null,
                ]);
            });

            $verification->update([
                'status' => 'accepted',
                'verified_by' => $reviewer->id,
                'reviewed_at' => now(),
                'notes' => trim($verification->notes."\n\nAdministrator: ".($notes ?: 'Accepted')),
            ]);

            $this->refreshCourtStatus($verification->court, $reviewer);
            AuditService::record('court.verification_accepted', $verification);

            return $verification->fresh(['claims', 'court']);
        });
    }

    public function reject(CourtVerification $verification, User $reviewer, string $notes): CourtVerification
    {
        return DB::transaction(function () use ($verification, $reviewer, $notes) {
            $verification = CourtVerification::query()->lockForUpdate()->findOrFail($verification->id);
            $verification->claims()->where('status', 'pending')->update(['status' => 'rejected']);
            $verification->update([
                'status' => 'rejected',
                'verified_by' => $reviewer->id,
                'reviewed_at' => now(),
                'notes' => trim($verification->notes."\n\nAdministrator: ".$notes),
            ]);
            $this->refreshCourtStatus($verification->court, $reviewer);
            AuditService::record('court.verification_rejected', $verification, ['notes' => $notes]);

            return $verification->fresh(['claims', 'court']);
        });
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function invalidate(Court $court, array $fields, string $reason): void
    {
        $fields = array_values(array_intersect(array_keys(CourtVerificationClaim::REQUIRED_FIELDS), array_unique($fields)));

        if ($fields === []) {
            return;
        }

        DB::transaction(function () use ($court, $fields, $reason) {
            $invalidated = $court->verificationClaims()
                ->whereIn('field_key', $fields)
                ->where('status', 'accepted')
                ->whereNull('invalidated_at')
                ->update([
                    'status' => 'invalidated',
                    'invalidated_at' => now(),
                    'invalidation_reason' => $reason,
                ]);

            if ($invalidated === 0) {
                return;
            }

            $court->update([
                'verification_status' => 'pending',
                'verified_at' => null,
                'verified_by' => null,
                'verification_invalidated_at' => now(),
                'status' => $court->status === CourtStatus::Published ? CourtStatus::PendingVerification : $court->status,
                'published_at' => $court->status === CourtStatus::Published ? null : $court->published_at,
            ]);

            AuditService::record('court.verification_invalidated', $court, [
                'fields' => $fields,
                'reason' => $reason,
            ]);
        });
    }

    public function refreshCourtStatus(Court $court, ?User $reviewer = null): void
    {
        $accepted = $court->verificationClaims()
            ->where('status', 'accepted')
            ->whereNull('invalidated_at')
            ->pluck('field_key')
            ->unique();

        $complete = collect(array_keys(CourtVerificationClaim::REQUIRED_FIELDS))
            ->every(fn (string $field) => $accepted->contains($field));

        $court->update([
            'verification_status' => $complete ? 'verified' : ($accepted->isNotEmpty() ? 'partially_verified' : 'unverified'),
            'verified_by' => $complete ? $reviewer?->id : null,
            'verified_at' => $complete ? now() : null,
            'verification_invalidated_at' => $complete ? null : $court->verification_invalidated_at,
        ]);
    }

    public function valueHash(Court $court, string $field): string
    {
        $court->loadMissing([
            'amenities',
            'photos',
            'operatingHours',
            'units.scheduleRules',
        ]);

        $value = match ($field) {
            'identity' => [$court->name, $court->short_description, $court->description],
            'address' => [$court->address_line, $court->barangay, $court->municipality, $court->province, $court->postal_code],
            'map_location' => [$court->latitude, $court->longitude, $court->google_maps_url],
            'court_type' => [$court->environment, $court->venue_type, $court->units->map->only(['name', 'environment'])->values()->all()],
            'operating_hours' => $court->operatingHours->sortBy('day_of_week')->map->only(['day_of_week', 'opens_at', 'closes_at', 'is_closed'])->values()->all(),
            'rental_rate' => $this->scheduleValues($court)
                ->map(fn (array $rule) => ['price_centavos' => $rule['price_centavos']])
                ->unique()
                ->values()
                ->all(),
            'schedule' => $this->scheduleValues($court)->values()->all(),
            'contact' => [$court->phone, $court->email, $court->facebook_url],
            'photos' => $court->photos->sortBy('id')->map->only(['path', 'rights_confirmed_at'])->values()->all(),
            'amenities' => $court->amenities->sortBy('slug')->pluck('slug')->values()->all(),
            default => null,
        };

        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function scheduleValues(Court $court): Collection
    {
        return $court->units
            ->flatMap(fn ($unit) => $unit->scheduleRules->map(fn ($rule) => [
                'unit_id' => $unit->id,
                'day_of_week' => $rule->day_of_week,
                'starts_at' => $rule->starts_at,
                'ends_at' => $rule->ends_at,
                'slot_minutes' => $rule->slot_minutes,
                'price_centavos' => $rule->price_centavos,
                'valid_from' => $rule->valid_from?->toDateString(),
                'valid_until' => $rule->valid_until?->toDateString(),
                'is_active' => $rule->is_active,
            ]))
            ->sortBy(fn (array $rule) => implode('|', $rule));
    }
}

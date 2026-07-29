<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CourtStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtVerificationRequest;
use App\Http\Requests\StoreScheduleRuleRequest;
use App\Models\Amenity;
use App\Models\Court;
use App\Models\CourtBlackout;
use App\Models\CourtOperatingHour;
use App\Models\CourtPaymentMethod;
use App\Models\CourtPhoto;
use App\Models\CourtScheduleRule;
use App\Models\CourtUnit;
use App\Services\AuditService;
use App\Services\CourtVerificationService;
use App\Services\MediaStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        $courts = $request->user()->isAdmin()
            ? Court::withCount(['units', 'bookings'])->orderBy('name')->get()
            : $request->user()->courts()->withCount(['units', 'bookings'])->orderBy('name')->get();

        return view('owner.courts.index', compact('courts'));
    }

    public function create()
    {
        return view('owner.courts.form', ['court' => new Court, 'amenities' => Amenity::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->courtData($request);
        $court = Court::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'status' => CourtStatus::Draft,
            'verification_status' => 'unverified',
        ]);

        $court->managers()->attach($request->user()->id, ['role' => 'manager']);
        $court->amenities()->sync($data['amenities'] ?? []);
        AuditService::record('court.created', $court);

        return redirect()->route('owner.courts.manage', $court)->with('success', 'Draft court created. Complete its verified information before publication.');
    }

    public function edit(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);

        return view('owner.courts.form', [
            'court' => $court->load('amenities'),
            'amenities' => Amenity::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Court $court, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        $data = $this->courtData($request, $court);
        $court->fill(collect($data)->except('amenities')->all());
        $dirty = array_keys($court->getDirty());
        $court->save();

        $oldAmenities = $court->amenities()->pluck('amenities.id')->sort()->values()->all();
        $newAmenities = collect($data['amenities'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        $court->amenities()->sync($data['amenities'] ?? []);

        $claimFields = collect([
            'identity' => ['name', 'short_description', 'description'],
            'address' => ['address_line', 'barangay', 'municipality', 'province', 'postal_code'],
            'map_location' => ['latitude', 'longitude', 'google_maps_url'],
            'court_type' => ['environment', 'venue_type'],
            'contact' => ['phone', 'email', 'facebook_url'],
        ])->filter(fn (array $fields) => array_intersect($fields, $dirty) !== [])->keys()->all();

        if ($oldAmenities !== $newAmenities) {
            $claimFields[] = 'amenities';
        }

        $verification->invalidate($court, $claimFields, 'Published court details were edited by a venue manager.');
        AuditService::record('court.updated', $court);

        return redirect()->route('owner.courts.manage', $court)->with('success', 'Court details updated.');
    }

    public function manage(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);
        $court->load([
            'photos',
            'units.scheduleRules',
            'operatingHours',
            'blackouts.courtUnit',
            'paymentMethods',
            'verifications.claims',
            'verificationClaims',
            'amenities',
        ]);

        return view('owner.courts.manage', [
            'court' => $court,
            'publishabilityErrors' => $court->publishabilityErrors(),
        ]);
    }

    public function archive(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);
        $court->update(['status' => CourtStatus::Archived, 'archived_at' => now(), 'published_at' => null]);
        AuditService::record('court.archived', $court);

        return back()->with('success', 'Court archived and removed from public discovery.');
    }

    public function submitForVerification(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);
        $court->update(['status' => CourtStatus::PendingVerification, 'verification_status' => 'pending']);
        AuditService::record('court.submitted_for_verification', $court);

        return back()->with('success', 'Court submitted for administrator verification.');
    }

    public function storePhoto(
        Request $request,
        Court $court,
        MediaStorageService $media,
        CourtVerificationService $verification,
    ) {
        $this->authorizeCourt($request, $court);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'rights_confirmed' => ['accepted'],
        ]);

        if ($request->boolean('is_primary') || ! $court->photos()->exists()) {
            $court->photos()->update(['is_primary' => false]);
        }

        $stored = $media->store($request->file('photo'), "court-photos/{$court->id}", 'public');
        $photo = $court->photos()->create([
            'path' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'storage_url' => $stored['url'],
            'mime_type' => $stored['mime'],
            'size_bytes' => $stored['bytes'],
            'alt_text' => $data['alt_text'],
            'caption' => $data['caption'] ?? null,
            'is_primary' => $request->boolean('is_primary') || ! $court->photos()->exists(),
            'rights_confirmed_at' => now(),
            'sort_order' => (int) $court->photos()->max('sort_order') + 1,
        ]);
        $verification->invalidate($court, ['photos'], 'Court photo collection changed.');
        AuditService::record('court.photo_added', $photo);

        return back()->with('success', 'Actual court photo uploaded.');
    }

    public function destroyPhoto(
        Request $request,
        Court $court,
        CourtPhoto $photo,
        MediaStorageService $media,
        CourtVerificationService $verification,
    ) {
        $this->authorizeCourt($request, $court);
        abort_unless($photo->court_id === $court->id, 404);
        $media->delete($photo->path, $photo->storage_disk, $photo->storage_url);
        $photo->delete();

        if (! $court->photos()->where('is_primary', true)->exists()) {
            $court->photos()->oldest('sort_order')->first()?->update(['is_primary' => true]);
        }
        $verification->invalidate($court, ['photos'], 'Court photo collection changed.');

        return back()->with('success', 'Photo removed.');
    }

    public function storeUnit(Request $request, Court $court, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'environment' => ['nullable', 'in:indoor,outdoor'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unit = $court->units()->create($data + [
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) $court->units()->max('sort_order') + 1,
        ]);
        $verification->invalidate($court, ['court_type', 'schedule', 'rental_rate'], 'Playable court inventory changed.');
        AuditService::record('court.unit_added', $unit);

        return back()->with('success', 'Playable court added.');
    }

    public function destroyUnit(Request $request, Court $court, CourtUnit $unit, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        abort_unless($unit->court_id === $court->id, 404);
        abort_if($unit->bookings()->exists(), 422, 'Archive this unit instead because it has reservation history.');
        $unit->delete();
        $verification->invalidate($court, ['court_type', 'schedule', 'rental_rate'], 'Playable court inventory changed.');

        return back()->with('success', 'Playable court removed.');
    }

    public function updateHours(Request $request, Court $court, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        $data = $request->validate([
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'hours.*.is_closed' => ['nullable', 'boolean'],
        ]);

        foreach ($data['hours'] as $day => $hours) {
            $closed = filter_var($hours['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $closed && (empty($hours['opens_at']) || empty($hours['closes_at']))) {
                throw ValidationException::withMessages([
                    "hours.{$day}.opens_at" => 'Open days require both opening and closing times.',
                ]);
            }

            if (! $closed && $hours['closes_at'] <= $hours['opens_at']) {
                throw ValidationException::withMessages([
                    "hours.{$day}.closes_at" => 'Closing time must be later than opening time.',
                ]);
            }

            $rules = $court->scheduleRules()
                ->where('court_schedule_rules.day_of_week', (int) $day)
                ->where('court_schedule_rules.is_active', true)
                ->get();

            if ($rules->isNotEmpty() && $closed) {
                throw ValidationException::withMessages([
                    "hours.{$day}.is_closed" => 'Deactivate schedule rules before closing this day.',
                ]);
            }

            foreach ($rules as $rule) {
                if ($rule->starts_at < $hours['opens_at'] || $rule->ends_at > $hours['closes_at']) {
                    throw ValidationException::withMessages([
                        "hours.{$day}.opens_at" => 'Operating hours must contain every active booking window for this day.',
                    ]);
                }
            }

            CourtOperatingHour::updateOrCreate(
                ['court_id' => $court->id, 'day_of_week' => (int) $day],
                [
                    'opens_at' => $closed ? null : ($hours['opens_at'] ?? null),
                    'closes_at' => $closed ? null : ($hours['closes_at'] ?? null),
                    'is_closed' => $closed,
                ],
            );
        }

        $verification->invalidate($court, ['operating_hours'], 'Operating hours changed.');
        AuditService::record('court.hours_updated', $court);

        return back()->with('success', 'Operating hours updated.');
    }

    public function storeSchedule(StoreScheduleRuleRequest $request, Court $court, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        $data = $request->validated();
        abort_unless($court->units()->whereKey($data['court_unit_id'])->exists(), 404);

        $hours = $court->operatingHours()->where('day_of_week', $data['day_of_week'])->first();
        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            throw ValidationException::withMessages(['day_of_week' => 'Set open operating hours for this day before adding a schedule.']);
        }

        if ($data['starts_at'] < substr($hours->opens_at, 0, 5) || $data['ends_at'] > substr($hours->closes_at, 0, 5)) {
            throw ValidationException::withMessages(['starts_at' => 'The schedule must stay inside the venue operating hours.']);
        }

        $start = CarbonImmutable::createFromFormat('H:i', $data['starts_at']);
        $end = CarbonImmutable::createFromFormat('H:i', $data['ends_at']);
        $windowMinutes = $start->diffInMinutes($end);
        if ($windowMinutes % (int) $data['slot_minutes'] !== 0) {
            throw ValidationException::withMessages(['slot_minutes' => 'The booking window must divide evenly into the selected slot length.']);
        }

        $overlap = CourtScheduleRule::query()
            ->where('court_unit_id', $data['court_unit_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->get()
            ->contains(function (CourtScheduleRule $rule) use ($data) {
                $timeOverlaps = $data['starts_at'] < substr($rule->ends_at, 0, 5)
                    && $data['ends_at'] > substr($rule->starts_at, 0, 5);
                $existingFrom = $rule->valid_from?->toDateString() ?? '0001-01-01';
                $existingUntil = $rule->valid_until?->toDateString() ?? '9999-12-31';
                $newFrom = $data['valid_from'] ?? '0001-01-01';
                $newUntil = $data['valid_until'] ?? '9999-12-31';

                return $timeOverlaps && $newFrom <= $existingUntil && $newUntil >= $existingFrom;
            });

        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'This rule overlaps another active schedule for the same playable court.']);
        }

        $rule = CourtScheduleRule::create([
            ...collect($data)->except('price')->all(),
            'price_centavos' => (int) round(((float) $data['price']) * 100),
            'is_active' => true,
        ]);
        $verification->invalidate($court, ['schedule', 'rental_rate'], 'Schedule or rental pricing changed.');
        AuditService::record('court.schedule_added', $rule);

        return back()->with('success', 'Availability and rate rule added.');
    }

    public function destroySchedule(Request $request, Court $court, CourtScheduleRule $schedule, CourtVerificationService $verification)
    {
        $this->authorizeCourt($request, $court);
        abort_unless($schedule->courtUnit->court_id === $court->id, 404);
        $schedule->update(['is_active' => false]);
        $verification->invalidate($court, ['schedule', 'rental_rate'], 'Schedule or rental pricing changed.');

        return back()->with('success', 'Schedule rule deactivated.');
    }

    public function storeBlackout(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);
        $data = $request->validate([
            'court_unit_id' => ['nullable', 'integer', 'exists:court_units,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
        ]);
        if ($data['court_unit_id'] ?? null) {
            abort_unless($court->units()->whereKey($data['court_unit_id'])->exists(), 404);
        }

        $blackout = $court->blackouts()->create($data + [
            'is_public' => $request->boolean('is_public'),
            'created_by' => $request->user()->id,
        ]);
        AuditService::record('court.blackout_added', $blackout);

        return back()->with('success', 'Unavailable period blocked.');
    }

    public function destroyBlackout(Request $request, Court $court, CourtBlackout $blackout)
    {
        $this->authorizeCourt($request, $court);
        abort_unless($blackout->court_id === $court->id, 404);
        $blackout->delete();

        return back()->with('success', 'Unavailable period removed.');
    }

    public function storePaymentMethod(Request $request, Court $court)
    {
        $this->authorizeCourt($request, $court);
        $data = $request->validate([
            'type' => ['required', 'in:cash,gcash,bank,other'],
            'label' => ['required', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_reference' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $method = $court->paymentMethods()->create($data + ['is_active' => true]);
        AuditService::record('court.payment_method_added', $method);

        return back()->with('success', 'Payment method added.');
    }

    public function destroyPaymentMethod(Request $request, Court $court, CourtPaymentMethod $method)
    {
        $this->authorizeCourt($request, $court);
        abort_unless($method->court_id === $court->id, 404);
        $method->update(['is_active' => false]);

        return back()->with('success', 'Payment method deactivated.');
    }

    public function storeVerification(
        StoreCourtVerificationRequest $request,
        Court $court,
        MediaStorageService $media,
        CourtVerificationService $verificationService,
    ) {
        $this->authorizeCourt($request, $court);
        $data = $request->validated();

        $stored = $request->file('evidence')
            ? $media->store($request->file('evidence'), "court-verifications/{$court->id}", 'private')
            : null;

        $verification = $court->verifications()->create([
            'type' => $data['type'],
            'source_url' => $data['source_url'] ?? null,
            'notes' => $data['notes'],
            'evidence_path' => $stored['path'] ?? null,
            'evidence_disk' => $stored['disk'] ?? 'local',
            'evidence_mime' => $stored['mime'] ?? null,
            'evidence_bytes' => $stored['bytes'] ?? null,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
        ]);
        $verificationService->attachClaims($verification, $data['facts']);
        AuditService::record('court.verification_submitted', $verification);

        return back()->with('success', 'Verification evidence submitted.');
    }

    private function courtData(Request $request, ?Court $court = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address_line' => ['required', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'municipality' => ['required', Rule::in(['Kabacan'])],
            'province' => ['required', Rule::in(['Cotabato'])],
            'postal_code' => ['required', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'url', 'max:2048'],
            'environment' => ['required', 'in:indoor,outdoor'],
            'venue_type' => ['required', 'in:dedicated,multipurpose'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'payment_policy' => ['required', 'in:pay_on_site,proof_required,either'],
            'cancellation_cutoff_hours' => ['required', 'integer', 'between:0,168'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
        ]);
    }

    private function authorizeCourt(Request $request, Court $court): void
    {
        abort_unless($court->isManagedBy($request->user()), 403);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Court::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

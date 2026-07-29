<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Court;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class CourtDirectoryController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'environment' => ['nullable', 'in:indoor,outdoor'],
            'amenity' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:+60 days'],
        ]);

        $query = Court::published()
            ->with(['primaryPhoto', 'amenities'])
            ->withAvg('publishedReviews', 'rating')
            ->withMin('scheduleRules as starting_price_centavos', 'price_centavos');

        $query->when($filters['q'] ?? null, fn ($q, $search) => $q->where(function ($nested) use ($search) {
            $nested->where('name', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%")
                ->orWhere('address_line', 'like', "%{$search}%");
        }));

        $query->when($filters['barangay'] ?? null, fn ($q, $barangay) => $q->where('barangay', $barangay));
        $query->when($filters['environment'] ?? null, fn ($q, $environment) => $q->where('environment', $environment));
        $query->when($filters['amenity'] ?? null, fn ($q, $amenity) => $q->whereHas('amenities', fn ($a) => $a->where('slug', $amenity)));
        $query->when($filters['min_price'] ?? null, fn ($q, $price) => $q->whereHas('scheduleRules', fn ($r) => $r->where('price_centavos', '>=', (int) round($price * 100))));
        $query->when($filters['max_price'] ?? null, fn ($q, $price) => $q->whereHas('scheduleRules', fn ($r) => $r->where('price_centavos', '<=', (int) round($price * 100))));

        $courts = $query->orderByDesc('is_featured')->orderBy('name')->limit(50)->get();

        if ($filters['date'] ?? null) {
            $courts = $courts
                ->filter(fn (Court $court) => collect($availability->forCourt($court, $filters['date'])['slots'])->contains('status', 'available'))
                ->values();
        }

        return view('courts.index', [
            'courts' => $courts,
            'amenities' => Amenity::orderBy('name')->get(),
            'barangays' => Court::published()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay'),
            'filters' => $filters,
        ]);
    }

    public function show(Court $court)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);

        $court->load([
            'photos',
            'amenities',
            'operatingHours',
            'activeUnits',
            'paymentMethods' => fn ($query) => $query->where('is_active', true),
            'publishedReviews.user',
            'verifications' => fn ($query) => $query->where('status', 'accepted'),
        ])->loadAvg('publishedReviews', 'rating');

        return view('courts.show', compact('court'));
    }
}

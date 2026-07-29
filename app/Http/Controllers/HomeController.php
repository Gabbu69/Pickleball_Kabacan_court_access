<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use App\Models\Court;

class HomeController extends Controller
{
    public function __invoke()
    {
        $featuredCourts = Court::published()
            ->with(['primaryPhoto', 'amenities'])
            ->withAvg('publishedReviews', 'rating')
            ->withMin('scheduleRules as starting_price_centavos', 'price_centavos')
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->take(6)
            ->get();

        return view('home', [
            'featuredCourts' => $featuredCourts,
            'posts' => ContentPost::published()
                ->where(fn ($query) => $query
                    ->whereNull('court_id')
                    ->orWhereHas('court', fn ($court) => $court->published()))
                ->latest('published_at')
                ->take(3)
                ->get(),
            'courtCount' => Court::published()->count(),
            'barangayCount' => Court::published()->whereNotNull('barangay')->distinct('barangay')->count('barangay'),
        ]);
    }
}

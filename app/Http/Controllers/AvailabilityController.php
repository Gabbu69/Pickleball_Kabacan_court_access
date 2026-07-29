<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, Court $court, AvailabilityService $availability)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:+60 days'],
        ]);

        return response()->json($availability->forCourt($court, $data['date']));
    }
}

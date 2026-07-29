<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function store(Request $request, Court $court)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);
        $request->user()->favoriteCourts()->syncWithoutDetaching($court->id);

        return back()->with('success', 'Court saved to favorites.');
    }

    public function destroy(Request $request, Court $court)
    {
        $request->user()->favoriteCourts()->detach($court->id);

        return back()->with('success', 'Court removed from favorites.');
    }
}

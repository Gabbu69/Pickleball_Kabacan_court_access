<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index()
    {
        return view('admin.reviews.index', [
            'reviews' => Review::with(['user', 'court', 'booking'])->latest()->paginate(20),
        ]);
    }

    public function update(Request $request, Review $review)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'published', 'hidden'])]]);
        $review->update([
            'status' => $data['status'],
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);
        AuditService::record('review.moderated', $review, $data);

        return back()->with('success', 'Review moderation updated.');
    }
}

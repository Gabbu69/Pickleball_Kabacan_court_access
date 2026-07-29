<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicContentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['announcement', 'promotion', 'tournament', 'maintenance'])],
        ]);

        return view('content.index', [
            'posts' => ContentPost::published()
                ->with('court')
                ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->latest('published_at')
                ->paginate(12)
                ->withQueryString(),
            'type' => $data['type'] ?? null,
        ]);
    }

    public function show(ContentPost $post)
    {
        abort_unless($post->is_published && $post->published_at, 404);

        return view('content.show', ['post' => $post->load('court')]);
    }
}

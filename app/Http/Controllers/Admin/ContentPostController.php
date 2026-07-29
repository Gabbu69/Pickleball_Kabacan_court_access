<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Models\Court;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentPostController extends Controller
{
    public function index()
    {
        return view('admin.content.index', [
            'posts' => ContentPost::with('court')->latest()->paginate(20),
            'courts' => Court::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        $post = ContentPost::create([
            ...collect($data)->except(['image', 'is_published'])->all(),
            'created_by' => $request->user()->id,
            'slug' => $this->uniqueSlug($data['title']),
            'image_path' => $request->file('image') ? 'storage/'.$request->file('image')->store('content', 'public') : null,
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? now() : null,
        ]);
        AuditService::record('content.created', $post);

        return back()->with('success', 'Content item created.');
    }

    public function update(Request $request, ContentPost $post)
    {
        $data = $this->data($request);
        $values = collect($data)->except(['image', 'is_published'])->all() + [
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? ($post->published_at ?? now()) : null,
        ];

        if ($request->hasFile('image')) {
            if ($post->image_path) {
                Storage::disk('public')->delete(Str::after($post->image_path, 'storage/'));
            }
            $values['image_path'] = 'storage/'.$request->file('image')->store('content', 'public');
        }

        $post->update($values);
        AuditService::record('content.updated', $post);

        return back()->with('success', 'Content item updated.');
    }

    public function destroy(ContentPost $post)
    {
        if ($post->image_path) {
            Storage::disk('public')->delete(Str::after($post->image_path, 'storage/'));
        }
        $post->delete();

        return back()->with('success', 'Content item removed.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'type' => ['required', Rule::in(['announcement', 'promotion', 'tournament', 'maintenance'])],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $index = 2;
        while (ContentPost::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}

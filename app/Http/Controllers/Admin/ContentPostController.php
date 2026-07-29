<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Models\Court;
use App\Services\AuditService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
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

    public function store(Request $request, MediaStorageService $media)
    {
        $data = $this->data($request);
        $this->ensurePublishableCourt($data, $request);
        $stored = $request->file('image') ? $media->store($request->file('image'), 'content', 'public') : null;
        $post = ContentPost::create([
            ...collect($data)->except(['image', 'is_published'])->all(),
            'created_by' => $request->user()->id,
            'slug' => $this->uniqueSlug($data['title']),
            'image_path' => $stored['path'] ?? null,
            'image_disk' => $stored['disk'] ?? 'public',
            'image_url' => $stored['url'] ?? null,
            'image_mime' => $stored['mime'] ?? null,
            'image_bytes' => $stored['bytes'] ?? null,
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? now() : null,
        ]);
        AuditService::record('content.created', $post);

        return back()->with('success', 'Content item created.');
    }

    public function update(Request $request, ContentPost $post, MediaStorageService $media)
    {
        $data = $this->data($request);
        $this->ensurePublishableCourt($data, $request);
        $previousMedia = [$post->image_path, $post->image_disk, $post->image_url];
        $values = collect($data)->except(['image', 'is_published'])->all() + [
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? ($post->published_at ?? now()) : null,
        ];

        if ($request->hasFile('image')) {
            $stored = $media->store($request->file('image'), 'content', 'public');
            $values += [
                'image_path' => $stored['path'],
                'image_disk' => $stored['disk'],
                'image_url' => $stored['url'],
                'image_mime' => $stored['mime'],
                'image_bytes' => $stored['bytes'],
            ];
        }

        $post->update($values);

        if (isset($stored) && $previousMedia[0]) {
            $media->delete(...$previousMedia);
        }
        AuditService::record('content.updated', $post);

        return back()->with('success', 'Content item updated.');
    }

    public function destroy(ContentPost $post, MediaStorageService $media)
    {
        if ($post->image_path) {
            $media->delete($post->image_path, $post->image_disk, $post->image_url);
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }

    private function ensurePublishableCourt(array $data, Request $request): void
    {
        if (! $request->boolean('is_published') || empty($data['court_id'])) {
            return;
        }

        $court = Court::findOrFail($data['court_id']);
        abort_unless($court->isPubliclyDiscoverable(), 422, 'Content cannot expose an unpublished or unverified court.');
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

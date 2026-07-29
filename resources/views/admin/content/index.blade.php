<x-app-layout>
    <x-slot name="header"><div><p class="eyebrow">Community publishing</p><h1 class="dashboard-title">Announcements and events</h1></div></x-slot>
    <details class="manage-section" open>
        <summary><span>＋</span><div><strong>Create content</strong><small>Announcement, promotion, tournament, or maintenance notice</small></div><i>＋</i></summary>
        <div class="manage-content">
            <form method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data" class="form-grid">@csrf
                <div><label for="new-content-type">Type</label><select id="new-content-type" class="form-input" name="type">@foreach(['announcement','promotion','tournament','maintenance'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div><label for="new-content-court">Related court</label><select id="new-content-court" class="form-input" name="court_id"><option value="">Platform-wide</option>@foreach($courts as $court)<option value="{{ $court->id }}">{{ $court->name }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label for="new-content-title">Title</label><input id="new-content-title" class="form-input" name="title" required></div>
                <div class="sm:col-span-2"><label for="new-content-excerpt">Short summary</label><input id="new-content-excerpt" class="form-input" name="excerpt" maxlength="320"></div>
                <div class="sm:col-span-2"><label for="new-content-body">Content</label><textarea id="new-content-body" class="form-input min-h-36" name="body" required></textarea></div>
                <div><label for="new-content-starts">Starts <span>(optional)</span></label><input id="new-content-starts" class="form-input" type="datetime-local" name="starts_at"></div>
                <div><label for="new-content-ends">Ends <span>(optional)</span></label><input id="new-content-ends" class="form-input" type="datetime-local" name="ends_at"></div>
                <div><label for="new-content-image">Image <span>(optional)</span></label><input id="new-content-image" class="form-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
                <label class="check-line self-end"><input type="checkbox" name="is_published" value="1"> Publish now</label>
                <button class="btn-primary w-fit">Create update</button>
            </form>
        </div>
    </details>

    <div class="space-y-5 mt-7">
        @foreach ($posts as $post)
            <details class="manage-section">
                <summary><span>{{ strtoupper(substr($post->type,0,2)) }}</span><div><strong>{{ $post->title }}</strong><small>{{ ucfirst($post->type) }} · {{ $post->is_published ? 'Published' : 'Draft' }}</small></div><i>＋</i></summary>
                <div class="manage-content">
                    <form method="POST" action="{{ route('admin.content.update', $post) }}" enctype="multipart/form-data" class="form-grid">@csrf @method('PUT')
                        <div><label for="content-type-{{ $post->id }}">Type</label><select id="content-type-{{ $post->id }}" class="form-input" name="type">@foreach(['announcement','promotion','tournament','maintenance'] as $type)<option value="{{ $type }}" @selected($post->type === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                        <div><label for="content-court-{{ $post->id }}">Related court</label><select id="content-court-{{ $post->id }}" class="form-input" name="court_id"><option value="">Platform-wide</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected($post->court_id === $court->id)>{{ $court->name }}</option>@endforeach</select></div>
                        <div class="sm:col-span-2"><label for="content-title-{{ $post->id }}">Title</label><input id="content-title-{{ $post->id }}" class="form-input" name="title" value="{{ $post->title }}" required></div>
                        <div class="sm:col-span-2"><label for="content-excerpt-{{ $post->id }}">Summary</label><input id="content-excerpt-{{ $post->id }}" class="form-input" name="excerpt" value="{{ $post->excerpt }}"></div>
                        <div class="sm:col-span-2"><label for="content-body-{{ $post->id }}">Content</label><textarea id="content-body-{{ $post->id }}" class="form-input min-h-36" name="body" required>{{ $post->body }}</textarea></div>
                        <div><label for="content-starts-{{ $post->id }}">Starts</label><input id="content-starts-{{ $post->id }}" class="form-input" type="datetime-local" name="starts_at" value="{{ $post->starts_at?->format('Y-m-d\TH:i') }}"></div>
                        <div><label for="content-ends-{{ $post->id }}">Ends</label><input id="content-ends-{{ $post->id }}" class="form-input" type="datetime-local" name="ends_at" value="{{ $post->ends_at?->format('Y-m-d\TH:i') }}"></div>
                        <div><label for="content-image-{{ $post->id }}">Replace image</label><input id="content-image-{{ $post->id }}" class="form-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
                        <label class="check-line self-end"><input type="checkbox" name="is_published" value="1" @checked($post->is_published)> Published</label>
                        <button class="btn-primary w-fit">Save update</button>
                    </form>
                    <form method="POST" action="{{ route('admin.content.destroy', $post) }}" class="mt-4">@csrf @method('DELETE')<button class="btn-danger">Delete content</button></form>
                </div>
            </details>
        @endforeach
    </div>
    <div class="mt-8">{{ $posts->links() }}</div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header"><div><p class="eyebrow">Community publishing</p><h1 class="dashboard-title">Announcements and events</h1></div></x-slot>
    <details class="manage-section" open>
        <summary><span>＋</span><div><strong>Create content</strong><small>Announcement, promotion, tournament, or maintenance notice</small></div><i>＋</i></summary>
        <div class="manage-content">
            <form method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data" class="form-grid">@csrf
                <div><label>Type</label><select class="form-input" name="type">@foreach(['announcement','promotion','tournament','maintenance'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div><label>Related court</label><select class="form-input" name="court_id"><option value="">Platform-wide</option>@foreach($courts as $court)<option value="{{ $court->id }}">{{ $court->name }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label>Title</label><input class="form-input" name="title" required></div>
                <div class="sm:col-span-2"><label>Short summary</label><input class="form-input" name="excerpt" maxlength="320"></div>
                <div class="sm:col-span-2"><label>Content</label><textarea class="form-input min-h-36" name="body" required></textarea></div>
                <div><label>Starts <span>(optional)</span></label><input class="form-input" type="datetime-local" name="starts_at"></div>
                <div><label>Ends <span>(optional)</span></label><input class="form-input" type="datetime-local" name="ends_at"></div>
                <div><label>Image <span>(optional)</span></label><input class="form-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
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
                        <div><label>Type</label><select class="form-input" name="type">@foreach(['announcement','promotion','tournament','maintenance'] as $type)<option value="{{ $type }}" @selected($post->type === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                        <div><label>Related court</label><select class="form-input" name="court_id"><option value="">Platform-wide</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected($post->court_id === $court->id)>{{ $court->name }}</option>@endforeach</select></div>
                        <div class="sm:col-span-2"><label>Title</label><input class="form-input" name="title" value="{{ $post->title }}" required></div>
                        <div class="sm:col-span-2"><label>Summary</label><input class="form-input" name="excerpt" value="{{ $post->excerpt }}"></div>
                        <div class="sm:col-span-2"><label>Content</label><textarea class="form-input min-h-36" name="body" required>{{ $post->body }}</textarea></div>
                        <div><label>Starts</label><input class="form-input" type="datetime-local" name="starts_at" value="{{ $post->starts_at?->format('Y-m-d\TH:i') }}"></div>
                        <div><label>Ends</label><input class="form-input" type="datetime-local" name="ends_at" value="{{ $post->ends_at?->format('Y-m-d\TH:i') }}"></div>
                        <div><label>Replace image</label><input class="form-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
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

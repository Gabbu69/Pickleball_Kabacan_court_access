<x-app-layout>
    <x-slot name="header"><div><p class="eyebrow">Community trust</p><h1 class="dashboard-title">Verified review moderation</h1></div></x-slot>
    <div class="space-y-5">
        @forelse ($reviews as $review)
            <article class="panel flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl"><div class="flex flex-wrap items-center gap-2"><span class="rating-chip">★ {{ $review->rating }}</span><span class="status status-{{ $review->status }}">{{ ucfirst($review->status) }}</span><strong>{{ $review->user->name }}</strong></div><h2 class="mt-3 text-xl font-extrabold">{{ $review->court->name }}</h2><p class="mt-3 leading-7 text-slate-600">{{ $review->body }}</p><small class="mt-2 block text-slate-400">Verified booking {{ $review->booking->reference }}</small></div>
                <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="flex gap-2">@csrf @method('PATCH')<select class="form-input" name="status"><option value="pending" @selected($review->status === 'pending')>Pending review</option><option value="published" @selected($review->status === 'published')>Published</option><option value="hidden" @selected($review->status === 'hidden')>Hidden</option></select><button class="btn-outline">Update</button></form>
            </article>
        @empty
            <div class="panel panel-empty"><h2>No player reviews yet.</h2></div>
        @endforelse
    </div>
    <div class="mt-8">{{ $reviews->links() }}</div>
</x-app-layout>

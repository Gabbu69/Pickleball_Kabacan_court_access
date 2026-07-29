<x-app-layout>
    <x-slot name="header"><div><p class="eyebrow">Owner access</p><h1 class="dashboard-title">Court-owner applications</h1></div></x-slot>
    <div class="space-y-5">
        @forelse ($applications as $application)
            <article class="panel">
                <div class="flex flex-col gap-5 lg:flex-row lg:justify-between">
                    <div class="max-w-3xl"><div class="flex items-center gap-2"><span class="status status-{{ $application->status }}">{{ ucfirst($application->status) }}</span><strong>{{ $application->user->name }} · {{ $application->user->email }}</strong></div><h2 class="mt-3 text-2xl font-extrabold">{{ $application->proposed_court_name }}</h2><p class="mt-3 text-sm leading-7 text-slate-600">{{ $application->message }}</p><a class="mt-3 inline-flex font-bold text-teal-700" href="{{ route('admin.owner-applications.evidence', $application) }}">Download private evidence ↗</a>@if($application->court)<p class="mt-2 text-sm">Existing listing: {{ $application->court->name }}</p>@endif</div>
                    @if($application->status === 'pending')
                        <div class="grid min-w-[18rem] gap-3">
                            <form method="POST" action="{{ route('admin.owner-applications.update', $application) }}" class="space-y-2">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><textarea class="form-input" name="reviewer_notes" required placeholder="Approval note and next steps"></textarea><button class="btn-primary w-full justify-center">Approve owner</button></form>
                            <form method="POST" action="{{ route('admin.owner-applications.update', $application) }}" class="space-y-2">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><textarea class="form-input" name="reviewer_notes" required placeholder="Reason for rejection"></textarea><button class="btn-danger w-full justify-center">Reject application</button></form>
                        </div>
                    @else
                        <div class="application-status"><div><strong>Reviewed {{ $application->reviewed_at?->format('M j, Y') }}</strong><p>{{ $application->reviewer_notes }}</p></div></div>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel panel-empty"><h2>No owner applications yet.</h2></div>
        @endforelse
    </div>
    <div class="mt-8">{{ $applications->links() }}</div>
</x-app-layout>

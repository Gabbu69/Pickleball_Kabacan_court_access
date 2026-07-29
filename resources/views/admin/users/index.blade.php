<x-app-layout>
    <x-slot name="header"><div><p class="eyebrow">Account management</p><h1 class="dashboard-title">Registered users and owners</h1></div></x-slot>
    <form method="GET" class="panel flex flex-col gap-3 sm:flex-row"><input class="form-input flex-1" name="q" value="{{ $search }}" placeholder="Search name, email, or phone"><button class="btn-primary">Search users</button></form>
    <div class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>User</th><th>Role</th><th>Activity</th><th>Status</th><th>Access controls</th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong><small>{{ $user->email }} · {{ $user->phone }}</small></td>
                        <td>{{ ucfirst($user->role->value) }}</td>
                        <td>{{ $user->bookings_count }} bookings · {{ $user->courts_count }} courts</td>
                        <td><span class="status status-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
                        <td><form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex min-w-[22rem] gap-2">@csrf @method('PATCH')<select class="form-input" name="role">@foreach(['player','owner','admin'] as $role)<option value="{{ $role }}" @selected($user->role->value === $role)>{{ ucfirst($role) }}</option>@endforeach</select><select class="form-input" name="status"><option value="active" @selected($user->status === 'active')>Active</option><option value="suspended" @selected($user->status === 'suspended')>Suspended</option></select><button class="btn-outline">Save</button></form></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-8">{{ $users->links() }}</div>
</x-app-layout>

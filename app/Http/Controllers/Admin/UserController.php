<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;
        $users = User::query()
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->withCount(['bookings', 'courts'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id && $request->input('status') !== 'active', 422, 'You cannot suspend your own account.');
        $data = $request->validate([
            'role' => ['required', Rule::in(['player', 'owner', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $user = DB::transaction(function () use ($user, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $removesActiveAdmin = $user->role->value === 'admin'
                && $user->status === 'active'
                && ($data['role'] !== 'admin' || $data['status'] !== 'active');

            if ($removesActiveAdmin) {
                $activeAdministrators = User::query()
                    ->where('role', 'admin')
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get(['id']);

                abort_if(
                    $activeAdministrators->count() <= 1,
                    422,
                    'The platform must retain at least one active administrator.',
                );
            }

            $user->update($data);

            return $user;
        });
        AuditService::record('user.access_updated', $user, $data);

        return back()->with('success', 'User access updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\CourtStatus;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->notification_email = $request->boolean('notification_email');

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->isAdmin()) {
                $activeAdministrators = User::query()
                    ->where('role', 'admin')
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($activeAdministrators->count() <= 1) {
                    throw ValidationException::withMessages([
                        'password' => 'Assign another active administrator before closing this account.',
                    ])->errorBag('userDeletion');
                }
            }

            $managedCourts = $user->courts()->withCount('managers')->get();

            foreach ($managedCourts as $court) {
                if ($court->managers_count <= 1) {
                    $court->update([
                        'status' => CourtStatus::Archived,
                        'published_at' => null,
                        'archived_at' => now(),
                    ]);
                }
            }

            $reference = (string) Str::uuid();
            AuditService::record('user.account_closed', $user, ['anonymized_reference' => $reference]);
            $user->favoriteCourts()->detach();
            $user->courts()->detach();
            $user->forceFill([
                'name' => 'Closed account',
                'email' => "closed+{$reference}@users.invalid",
                'phone' => null,
                'role' => 'player',
                'status' => 'closed',
                'closed_at' => now(),
                'anonymized_reference' => $reference,
                'notification_email' => false,
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
            ])->save();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerApplicationController extends Controller
{
    public function index()
    {
        return view('admin.owner-applications.index', [
            'applications' => OwnerApplication::with(['user', 'court'])->latest()->paginate(20),
        ]);
    }

    public function update(Request $request, OwnerApplication $ownerApplication)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'reviewer_notes' => ['required', 'string', 'max:2000'],
        ]);

        $ownerApplication->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $data['reviewer_notes'],
        ]);

        if ($data['status'] === 'approved') {
            $ownerApplication->user->update(['role' => 'owner', 'status' => 'active']);
            if ($ownerApplication->court_id) {
                $ownerApplication->court->managers()->syncWithoutDetaching([
                    $ownerApplication->user_id => ['role' => 'manager'],
                ]);
            }
        }

        $ownerApplication->user->notify(new PlatformNotification(
            'Court-owner application '.$data['status'],
            $data['reviewer_notes'],
            '/dashboard',
        ));
        AuditService::record('owner_application.'.$data['status'], $ownerApplication);

        return back()->with('success', 'Owner application updated.');
    }

    public function download(OwnerApplication $ownerApplication): StreamedResponse
    {
        abort_unless($ownerApplication->evidence_path && Storage::disk('local')->exists($ownerApplication->evidence_path), 404);

        return Storage::disk('local')->download($ownerApplication->evidence_path);
    }
}

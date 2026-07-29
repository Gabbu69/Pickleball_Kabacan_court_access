<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        $ownerApplication = DB::transaction(function () use ($ownerApplication, $request, $data) {
            $ownerApplication = OwnerApplication::query()->with(['user', 'court'])->lockForUpdate()->findOrFail($ownerApplication->id);
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

            AuditService::record('owner_application.'.$data['status'], $ownerApplication);

            return $ownerApplication;
        });

        try {
            $ownerApplication->user->notify(new PlatformNotification(
                'Court-owner application '.$data['status'],
                $data['reviewer_notes'],
                '/dashboard',
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Owner application updated.');
    }

    public function download(OwnerApplication $ownerApplication, MediaStorageService $media)
    {
        abort_unless($ownerApplication->evidence_path, 404);

        if ($ownerApplication->evidence_disk === 'vercel_blob_private') {
            $remote = $media->privateDownload(
                $ownerApplication->evidence_path,
                $ownerApplication->evidence_disk,
                $ownerApplication->evidence_url,
            );

            return response($remote->body(), 200, [
                'Content-Type' => $ownerApplication->evidence_mime ?: $remote->header('Content-Type', 'application/octet-stream'),
                'Content-Disposition' => 'inline; filename="owner-application-'.$ownerApplication->id.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        abort_unless(Storage::disk($ownerApplication->evidence_disk ?: 'local')->exists($ownerApplication->evidence_path), 404);

        return Storage::disk($ownerApplication->evidence_disk ?: 'local')->download($ownerApplication->evidence_path);
    }
}

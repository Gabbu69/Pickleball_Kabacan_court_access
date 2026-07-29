<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourtStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtVerification;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use App\Services\CourtVerificationService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CourtController extends Controller
{
    public function index()
    {
        return view('admin.courts.index', [
            'courts' => Court::with(['managers', 'verifications.claims', 'verificationClaims'])
                ->withCount(['units', 'bookings', 'photos'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function acceptVerification(
        Request $request,
        CourtVerification $verification,
        CourtVerificationService $verifications,
    ) {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        try {
            $verifications->accept($verification, $request->user(), $data['notes'] ?? null);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['verification' => $exception->getMessage()]);
        }

        return back()->with('success', 'Verification evidence accepted.');
    }

    public function rejectVerification(
        Request $request,
        CourtVerification $verification,
        CourtVerificationService $verifications,
    ) {
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $verifications->reject($verification, $request->user(), $data['notes']);

        return back()->with('success', 'Verification evidence rejected.');
    }

    public function publish(Court $court)
    {
        $errors = $court->publishabilityErrors();

        if ($errors) {
            return back()->withErrors(['publish' => 'Cannot publish: '.implode(', ', $errors).'.']);
        }

        $court->update([
            'status' => CourtStatus::Published,
            'verification_status' => 'verified',
            'published_at' => now(),
            'archived_at' => null,
        ]);
        try {
            $court->managers->each->notify(new PlatformNotification(
                'Court published',
                "{$court->name} is now visible in Kabacan PicklePlay.",
                '/owner/courts/'.$court->slug.'/manage',
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
        AuditService::record('court.published', $court);

        return back()->with('success', 'Verified court published.');
    }

    public function archive(Court $court)
    {
        $court->update(['status' => CourtStatus::Archived, 'published_at' => null, 'archived_at' => now()]);
        AuditService::record('court.archived_by_admin', $court);

        return back()->with('success', 'Court archived.');
    }

    public function feature(Request $request, Court $court)
    {
        $court->update(['is_featured' => $request->boolean('is_featured')]);
        AuditService::record('court.feature_changed', $court, ['is_featured' => $court->is_featured]);

        return back()->with('success', 'Featured status updated.');
    }

    public function downloadEvidence(CourtVerification $verification, MediaStorageService $media)
    {
        abort_unless($verification->evidence_path, 404);

        if ($verification->evidence_disk === 'vercel_blob_private') {
            $remote = $media->privateDownload($verification->evidence_path, $verification->evidence_disk);

            return response($remote->body(), 200, [
                'Content-Type' => $verification->evidence_mime ?: $remote->header('Content-Type', 'application/octet-stream'),
                'Content-Disposition' => 'inline; filename="court-verification-'.$verification->id.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        abort_unless(Storage::disk($verification->evidence_disk ?: 'local')->exists($verification->evidence_path), 404);

        return Storage::disk($verification->evidence_disk ?: 'local')->download($verification->evidence_path);
    }
}

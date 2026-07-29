<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourtStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtVerification;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourtController extends Controller
{
    public function index()
    {
        return view('admin.courts.index', [
            'courts' => Court::with(['managers', 'verifications'])->withCount(['units', 'bookings', 'photos'])->orderBy('name')->get(),
        ]);
    }

    public function acceptVerification(Request $request, CourtVerification $verification)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $verification->update([
            'status' => 'accepted',
            'verified_by' => $request->user()->id,
            'reviewed_at' => now(),
            'notes' => trim($verification->notes."\n\nAdministrator: ".($data['notes'] ?? 'Accepted')),
        ]);
        $verification->court->update([
            'verification_status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);
        AuditService::record('court.verification_accepted', $verification);

        return back()->with('success', 'Verification evidence accepted.');
    }

    public function rejectVerification(Request $request, CourtVerification $verification)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $verification->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'reviewed_at' => now(),
            'notes' => trim($verification->notes."\n\nAdministrator: ".$data['notes']),
        ]);
        $verification->court->update(['verification_status' => 'rejected']);
        AuditService::record('court.verification_rejected', $verification, ['notes' => $data['notes']]);

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
        $court->managers->each->notify(new PlatformNotification(
            'Court published',
            "{$court->name} is now visible in Kabacan PicklePlay.",
            '/owner/courts/'.$court->slug.'/manage',
        ));
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

    public function downloadEvidence(CourtVerification $verification): StreamedResponse
    {
        abort_unless($verification->evidence_path && Storage::disk('local')->exists($verification->evidence_path), 404);

        return Storage::disk('local')->download($verification->evidence_path);
    }
}

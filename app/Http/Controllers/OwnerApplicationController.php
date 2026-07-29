<?php

namespace App\Http\Controllers;

use App\Models\OwnerApplication;
use App\Services\AuditService;
use Illuminate\Http\Request;

class OwnerApplicationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'proposed_court_name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:30', 'max:3000'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $application = OwnerApplication::create([
            'user_id' => $request->user()->id,
            'court_id' => $data['court_id'] ?? null,
            'proposed_court_name' => $data['proposed_court_name'],
            'message' => $data['message'],
            'evidence_path' => $request->file('evidence')->store("owner-applications/{$request->user()->id}", 'local'),
            'status' => 'pending',
        ]);

        AuditService::record('owner_application.submitted', $application);

        return back()->with('success', 'Owner application submitted for administrator review.');
    }
}

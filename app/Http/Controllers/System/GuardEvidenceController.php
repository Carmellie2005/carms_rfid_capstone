<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\IncidentEvidence;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardEvidenceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'guard' && $request->user()->guardProfile, 403);

        $guard = $request->user()->guardProfile;

        return view('system.patrols.evidence', [
            'savedEvidence' => IncidentEvidence::query()
                ->where('guard_id', $guard->id)
                ->whereNull('incident_report_id')
                ->latest('captured_at')
                ->latest('id')
                ->paginate(6, ['*'], 'evidence_page'),
            'incidentReports' => IncidentReport::query()
                ->where('guard_id', $guard->id)
                ->with(['images.incidentEvidence', 'checkpoint'])
                ->latest('incident_at')
                ->latest('id')
                ->paginate(6, ['*'], 'incident_page'),
            'patrolLogs' => PatrolLog::query()
                ->where('guard_id', $guard->id)
                ->whereNotIn('status', ['pending_face', 'pending_selfie'])
                ->with(['checkpoint', 'checklistResponse.proofPhotos'])
                ->latest('scanned_at')
                ->latest('id')
                ->paginate(6, ['*'], 'patrol_page'),
        ]);
    }
}

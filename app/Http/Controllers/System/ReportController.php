<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $timezone = config('app.timezone');
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $timezone)
            : now($timezone)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $timezone)
            : now($timezone);

        $patrolQuery = PatrolLog::with(['securityGuard', 'checkpoint'])
            ->whereBetween('scanned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $incidentQuery = IncidentReport::with(['securityGuard', 'checkpoint'])
            ->whereBetween('incident_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $patrolStatusCounts = (clone $patrolQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $patrols = (clone $patrolQuery)
            ->latest('scanned_at')
            ->paginate(10, ['*'], 'patrol_page')
            ->withQueryString();

        $incidents = (clone $incidentQuery)
            ->latest('incident_at')
            ->paginate(5, ['*'], 'incident_page')
            ->withQueryString();

        return view('system.reports.index', [
            'from' => $from,
            'to' => $to,
            'patrols' => $patrols,
            'incidents' => $incidents,
            'guards' => Guard::orderBy('name')->get(),
            'checkpoints' => Checkpoint::orderBy('name')->get(),
            'summary' => [
                'valid' => (int) ($patrolStatusCounts['valid'] ?? 0),
                'suspicious' => (int) ($patrolStatusCounts['suspicious'] ?? 0),
                'invalid' => (int) ($patrolStatusCounts['invalid'] ?? 0),
                'profileIncomplete' => (int) ($patrolStatusCounts['profile_incomplete'] ?? 0),
                'outsideSchedule' => (int) ($patrolStatusCounts['outside_schedule'] ?? 0),
                'incidents' => (clone $incidentQuery)->count(),
            ],
        ]);
    }
}

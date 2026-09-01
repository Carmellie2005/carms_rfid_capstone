<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $timezone = config('app.timezone');
        $today = now($timezone)->toDateString();
        $trendDates = collect(range(6, 0))->map(fn (int $daysAgo) => now($timezone)->subDays($daysAgo));
        $checkpointActivity = Checkpoint::withCount([
            'patrolLogs as valid_patrols_count' => fn ($query) => $query->where('status', 'valid'),
        ])->orderByDesc('valid_patrols_count')->take(6)->get();

        return view('dashboard', [
            'stats' => [
                'activeGuards' => Guard::where('status', 'active')->count(),
                'activeCheckpoints' => Checkpoint::where('status', 'active')->count(),
                'todayPatrols' => PatrolLog::whereDate('scanned_at', $today)->where('status', 'valid')->count(),
                'openIncidents' => IncidentReport::whereIn('status', ['submitted', 'under_review'])->count(),
                'suspiciousScans' => PatrolLog::whereIn('status', ['invalid', 'suspicious', 'profile_incomplete', 'outside_schedule'])->count(),
            ],
            'recentPatrols' => PatrolLog::with(['securityGuard', 'checkpoint'])
                ->latest('scanned_at')
                ->take(8)
                ->get(),
            'recentIncidents' => IncidentReport::with(['securityGuard', 'checkpoint'])
                ->latest('incident_at')
                ->take(5)
                ->get(),
            'checkpointActivity' => $checkpointActivity,
            'analytics' => [
                'patrolTrend' => [
                    'labels' => $trendDates->map(fn ($date) => $date->format('M d'))->values(),
                    'data' => $trendDates
                        ->map(fn ($date) => PatrolLog::whereDate('scanned_at', $date->toDateString())->count())
                        ->values(),
                ],
                'scanStatus' => [
                    'labels' => ['Valid', 'Suspicious', 'Invalid', 'Profile Incomplete', 'Outside Schedule'],
                    'data' => [
                        PatrolLog::where('status', 'valid')->count(),
                        PatrolLog::where('status', 'suspicious')->count(),
                        PatrolLog::where('status', 'invalid')->count(),
                        PatrolLog::where('status', 'profile_incomplete')->count(),
                        PatrolLog::where('status', 'outside_schedule')->count(),
                    ],
                ],
                'incidentPriority' => [
                    'labels' => ['Low', 'Normal', 'High', 'Critical'],
                    'data' => [
                        IncidentReport::where('priority', 'low')->count(),
                        IncidentReport::where('priority', 'normal')->count(),
                        IncidentReport::where('priority', 'high')->count(),
                        IncidentReport::where('priority', 'critical')->count(),
                    ],
                ],
                'checkpointActivity' => [
                    'labels' => $checkpointActivity->pluck('code')->values(),
                    'data' => $checkpointActivity->pluck('valid_patrols_count')->values(),
                ],
            ],
        ]);
    }
}

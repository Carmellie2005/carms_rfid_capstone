<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Support\PatrolChecklist;
use App\Support\DutyRoster;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $timezone = config('app.timezone');
        $today = now($timezone)->toDateString();
        $todayMoment = now($timezone);
        $trendDates = collect(range(6, 0))->map(fn (int $daysAgo) => now($timezone)->subDays($daysAgo));
        $activeGuardProfiles = Guard::query()
            ->where('status', 'active')
            ->withCount([
                'patrolLogs as today_patrols_count' => fn ($query) => $query
                    ->whereDate('scanned_at', $today)
                    ->where('status', '!=', 'expired'),
            ])
            ->orderBy('employee_no')
            ->get();
        $onDutyGuards = DutyRoster::onDutyForDate($todayMoment, $activeGuardProfiles);
        $offDutyGuards = DutyRoster::offDutyForDate($todayMoment, $activeGuardProfiles);
        $activeCheckpointCount = Checkpoint::where('status', 'active')->count();
        $coveredCheckpointCount = Checkpoint::query()
            ->where('status', 'active')
            ->whereHas('patrolLogs', fn ($query) => $query
                ->whereDate('scanned_at', $today)
                ->where('status', 'valid'))
            ->count();
        $checkpointActivity = Checkpoint::withCount([
            'patrolLogs as valid_patrols_count' => fn ($query) => $query->where('status', 'valid'),
        ])->orderByDesc('valid_patrols_count')->take(6)->get();

        return view('dashboard', [
            'stats' => [
                'activeGuards' => $activeGuardProfiles->count(),
                'activeCheckpoints' => $activeCheckpointCount,
                'todayPatrols' => PatrolLog::whereDate('scanned_at', $today)->where('status', 'valid')->count(),
                'openIncidents' => IncidentReport::whereIn('status', ['submitted', 'under_review'])->count(),
            ],
            'onDutyGuards' => $onDutyGuards,
            'offDutyGuards' => $offDutyGuards,
            'recentPatrols' => PatrolLog::with(['securityGuard', 'checkpoint'])
                ->where('status', '!=', 'expired')
                ->latest('scanned_at')
                ->paginate(6, ['*'], 'recent_patrol_page')
                ->withQueryString(),
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
                'incidentPriority' => [
                    'labels' => array_values(PatrolChecklist::incidentPriorityOptions()),
                    'data' => [
                        IncidentReport::where('priority', 'low')->count(),
                        IncidentReport::where('priority', 'normal')->count(),
                        IncidentReport::whereIn('priority', ['high', 'critical'])->count(),
                    ],
                ],
                'checkpointActivity' => [
                    'labels' => $checkpointActivity->pluck('code')->values(),
                    'data' => $checkpointActivity->pluck('valid_patrols_count')->values(),
                ],
                'patrolCoverage' => [
                    'labels' => ['Scanned', 'Remaining'],
                    'data' => [
                        $coveredCheckpointCount,
                        max(0, $activeCheckpointCount - $coveredCheckpointCount),
                    ],
                ],
            ],
        ]);
    }
}

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

        $patrols = PatrolLog::with(['securityGuard', 'checkpoint'])
            ->whereBetween('scanned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('scanned_at')
            ->get();

        $incidents = IncidentReport::with(['securityGuard', 'checkpoint'])
            ->whereBetween('incident_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('incident_at')
            ->get();

        return view('system.reports.index', [
            'from' => $from,
            'to' => $to,
            'patrols' => $patrols,
            'incidents' => $incidents,
            'guards' => Guard::orderBy('name')->get(),
            'checkpoints' => Checkpoint::orderBy('name')->get(),
            'summary' => [
                'valid' => $patrols->where('status', 'valid')->count(),
                'suspicious' => $patrols->where('status', 'suspicious')->count(),
                'invalid' => $patrols->where('status', 'invalid')->count(),
                'profileIncomplete' => $patrols->where('status', 'profile_incomplete')->count(),
                'outsideSchedule' => $patrols->where('status', 'outside_schedule')->count(),
                'incidents' => $incidents->count(),
            ],
        ]);
    }
}

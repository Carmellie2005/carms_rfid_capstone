<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actions = $this->actions();
        $guards = Guard::orderBy('name')->get(['id', 'name', 'employee_no']);
        $selectedGuard = $this->selectedGuard($request);

        $logs = $this->auditLogQuery($request, $selectedGuard)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('system.audit.index', compact('actions', 'guards', 'logs', 'selectedGuard'));
    }

    public function downloadPdf(Request $request): Response
    {
        $selectedGuard = $this->selectedGuard($request);
        $logs = $this->auditLogQuery($request, $selectedGuard)
            ->latest()
            ->limit(500)
            ->get();

        $summary = [
            'total' => $logs->count(),
            'actions' => $logs
                ->groupBy('action')
                ->map(fn ($items, $action) => [
                    'action' => $this->labelFor($action),
                    'count' => $items->count(),
                ])
                ->sortBy('action')
                ->values(),
        ];

        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('system.audit.pdf', [
            'filters' => $this->activeFilters($request, $selectedGuard),
            'generatedAt' => now()->timezone(config('app.timezone')),
            'letterheadDataUri' => $this->letterheadDataUri(),
            'logs' => $logs,
            'selectedGuard' => $selectedGuard,
            'summary' => $summary,
        ])->setPaper('a4');

        AuditLogger::record('audit_report_exported', 'Audit trail PDF report exported.', $selectedGuard, [
            'guard_id' => $selectedGuard?->id,
            'employee_no' => $selectedGuard?->employee_no,
            'filters' => $request->only(['guard_id', 'action', 'date', 'search']),
            'record_count' => $logs->count(),
        ]);

        $filename = $this->pdfFilename($selectedGuard);

        if ($request->boolean('print')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    private function actions()
    {
        return AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }

    private function auditLogQuery(Request $request, ?Guard $guard): Builder
    {
        $missingGuard = $request->filled('guard_id') && ! $guard;

        return AuditLog::with('user')
            ->when($missingGuard, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($guard, fn (Builder $query) => $this->applyGuardFilter($query, $guard))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', $request->action))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('created_at', $request->date))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function (Builder $query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            });
    }

    private function applyGuardFilter(Builder $query, Guard $guard): void
    {
        $patrolLogIds = PatrolLog::query()
            ->select('id')
            ->where('guard_id', $guard->id);

        $incidentReportIds = IncidentReport::query()
            ->select('id')
            ->where('guard_id', $guard->id);

        $query->where(function (Builder $query) use ($guard, $patrolLogIds, $incidentReportIds) {
            $query->where(function (Builder $query) use ($guard) {
                $query->where('subject_type', Guard::class)
                    ->where('subject_id', $guard->id);
            })
                ->orWhere(function (Builder $query) use ($guard) {
                    $query->where('subject_type', User::class)
                        ->where('subject_id', $guard->user_id ?: 0);
                })
                ->orWhere(function (Builder $query) use ($patrolLogIds) {
                    $query->where('subject_type', PatrolLog::class)
                        ->whereIn('subject_id', $patrolLogIds);
                })
                ->orWhere(function (Builder $query) use ($incidentReportIds) {
                    $query->where('subject_type', IncidentReport::class)
                        ->whereIn('subject_id', $incidentReportIds);
                });

            if ($guard->user_id) {
                $query->orWhere('user_id', $guard->user_id);
            }

            $query->orWhere('actor_name', $guard->name)
                ->orWhere('properties->guard_id', $guard->id)
                ->orWhere('properties->employee_no', $guard->employee_no)
                ->orWhere('properties->rfid_uid', $guard->rfid_uid);
        });
    }

    private function selectedGuard(Request $request): ?Guard
    {
        if (! $request->filled('guard_id')) {
            return null;
        }

        return Guard::with('user')
            ->find($request->integer('guard_id'));
    }

    private function activeFilters(Request $request, ?Guard $selectedGuard): array
    {
        return [
            'guard' => $selectedGuard
                ? "{$selectedGuard->name} ({$selectedGuard->employee_no})"
                : ($request->filled('guard_id') ? 'Unknown guard' : 'All guards'),
            'action' => $request->filled('action') ? $this->labelFor($request->action) : 'All actions',
            'date' => $request->filled('date') ? $request->date : 'All dates',
            'search' => $request->filled('search') ? trim((string) $request->search) : 'None',
        ];
    }

    private function labelFor(?string $value): string
    {
        return Str::of($value ?: 'unknown')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function letterheadDataUri(): ?string
    {
        $path = public_path('images/pdf-letterhead.png');

        if (! file_exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    private function pdfFilename(?Guard $guard): string
    {
        $guardPart = $guard
            ? Str::slug($guard->employee_no.'-'.$guard->name)
            : 'all-guards';

        return sprintf('audit-trail-%s-%s.pdf', $guardPart, now()->format('Ymd-His'));
    }
}

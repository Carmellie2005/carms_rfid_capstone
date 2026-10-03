<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guard;
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
            'logs' => $logs,
            'selectedGuard' => $selectedGuard,
            'summary' => $summary,
        ])->setPaper([0, 0, 595.28, 841.89]);

        AuditLogger::record('audit_report_exported', 'Audit trail PDF report exported.', $selectedGuard, [
            'guard_id' => $selectedGuard?->id,
            'employee_no' => $selectedGuard?->employee_no,
            'filters' => $request->only(['guard_id', 'action', 'date', 'search']),
            'record_count' => $logs->count(),
        ]);

        $filename = $this->pdfFilename($selectedGuard);

        $response = $request->boolean('print')
            ? $pdf->stream($filename)
            : $pdf->download($filename);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function actions()
    {
        $query = AuditLog::query();
        $this->excludePendingSelfieRecords($query);

        return $query
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }

    private function auditLogQuery(Request $request, ?Guard $guard): Builder
    {
        $missingGuard = $request->filled('guard_id') && ! $guard;

        return AuditLog::with('user')
            ->where(fn (Builder $query) => $this->excludePendingSelfieRecords($query))
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
                        ->orWhere('properties->diagnostic', 'like', "%{$search}%");
                });
            });
    }

    private function excludePendingSelfieRecords(Builder $query): void
    {
        $hiddenStatuses = ['pending_face', 'pending_selfie'];

        $query
            ->where(function (Builder $query) use ($hiddenStatuses) {
                $query
                    ->whereNull('properties->result')
                    ->orWhereNotIn('properties->result', $hiddenStatuses);
            })
            ->where(function (Builder $query) use ($hiddenStatuses) {
                $query
                    ->whereNull('properties->status')
                    ->orWhereNotIn('properties->status', $hiddenStatuses);
            });
    }

    private function applyGuardFilter(Builder $query, Guard $guard): void
    {
        $query->where(function (Builder $query) use ($guard) {
            if ($guard->user_id) {
                $query->where('user_id', $guard->user_id)
                    ->orWhere('actor_name', $guard->name);

                return;
            }

            $query->where('actor_name', $guard->name);
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

    private function pdfFilename(?Guard $guard): string
    {
        $guardPart = $guard
            ? Str::slug($guard->employee_no.'-'.$guard->name)
            : 'all-guards';

        return sprintf('audit-trail-%s-%s.pdf', $guardPart, now()->format('Ymd-His'));
    }
}

<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PatrolLogController extends Controller
{
    public function index(Request $request): View
    {
        $isSupervisor = $request->user()->role === 'admin';
        $guardProfile = $request->user()->guardProfile;

        $logs = $this->patrolLogQuery($request, $isSupervisor, $guardProfile)
            ->latest('scanned_at')
            ->paginate($isSupervisor ? 12 : 6)
            ->withQueryString();

        return view('system.patrols.index', [
            'logs' => $logs,
            'guards' => $isSupervisor ? Guard::orderBy('name')->get() : collect([$guardProfile])->filter(),
            'checkpoints' => Checkpoint::orderBy('name')->get(),
            'isSupervisor' => $isSupervisor,
        ]);
    }

    public function downloadPdf(Request $request): Response
    {
        $isSupervisor = $request->user()->role === 'admin';
        $guardProfile = $request->user()->guardProfile;
        $selectedGuard = $this->selectedGuard($request, $isSupervisor, $guardProfile);
        $selectedCheckpoint = $request->filled('checkpoint_id')
            ? Checkpoint::find($request->integer('checkpoint_id'))
            : null;

        $logs = $this->patrolLogQuery($request, $isSupervisor, $guardProfile)
            ->latest('scanned_at')
            ->limit(500)
            ->get();

        $summary = [
            'total' => $logs->count(),
            'valid' => $logs->where('status', 'valid')->count(),
            'suspicious' => $logs->where('status', 'suspicious')->count(),
            'invalid' => $logs->where('status', 'invalid')->count(),
            'pending_face' => $logs->where('status', 'pending_face')->count(),
            'profile_incomplete' => $logs->where('status', 'profile_incomplete')->count(),
            'outside_schedule' => $logs->where('status', 'outside_schedule')->count(),
            'expired' => $logs->where('status', 'expired')->count(),
            'incidents' => $logs->filter(fn ($log) => $log->incidentReport)->count(),
        ];

        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('system.patrols.pdf', [
            'filters' => $this->activeFilters($request, $isSupervisor, $selectedGuard, $selectedCheckpoint),
            'generatedAt' => now()->timezone(config('app.timezone')),
            'isSupervisor' => $isSupervisor,
            'letterheadDataUri' => $this->letterheadDataUri(),
            'logs' => $logs,
            'selectedGuard' => $selectedGuard,
            'summary' => $summary,
        ])->setPaper('a4');

        AuditLogger::record('patrol_logs_exported', 'Patrol logs PDF report exported.', $selectedGuard, [
            'guard_id' => $selectedGuard?->id,
            'filters' => $request->only(['status', 'guard_id', 'checkpoint_id', 'date']),
            'record_count' => $logs->count(),
        ]);

        $filename = $this->pdfFilename($selectedGuard);

        if ($request->boolean('print')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    private function patrolLogQuery(Request $request, bool $isSupervisor, ?Guard $guardProfile): Builder
    {
        return PatrolLog::with(['securityGuard', 'checkpoint', 'checklistResponse', 'incidentReport'])
            ->when(! $isSupervisor, fn (Builder $query) => $query->where('guard_id', $guardProfile?->id ?? 0))
            ->when($isSupervisor && $request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($isSupervisor && $request->filled('guard_id'), fn (Builder $query) => $query->where('guard_id', $request->integer('guard_id')))
            ->when(! $isSupervisor && $request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('checkpoint_id'), fn (Builder $query) => $query->where('checkpoint_id', $request->integer('checkpoint_id')))
            ->when($request->filled('date'), function (Builder $query) use ($request) {
                $date = Carbon::parse($request->date('date')->toDateString(), config('app.timezone'));

                $query->whereBetween('scanned_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ]);
            });
    }

    private function selectedGuard(Request $request, bool $isSupervisor, ?Guard $guardProfile): ?Guard
    {
        if (! $isSupervisor) {
            return $guardProfile;
        }

        if (! $request->filled('guard_id')) {
            return null;
        }

        return Guard::find($request->integer('guard_id'));
    }

    private function activeFilters(Request $request, bool $isSupervisor, ?Guard $selectedGuard, ?Checkpoint $selectedCheckpoint): array
    {
        return [
            'guard' => match (true) {
                (bool) $selectedGuard => "{$selectedGuard->name} ({$selectedGuard->employee_no})",
                $isSupervisor && $request->filled('guard_id') => 'Unknown guard',
                $isSupervisor => 'All guards',
                default => 'My patrol logs',
            },
            'status' => $request->filled('status') ? $this->labelFor($request->status) : 'All statuses',
            'checkpoint' => $selectedCheckpoint
                ? "{$selectedCheckpoint->code} - {$selectedCheckpoint->name}"
                : ($request->filled('checkpoint_id') ? 'Unknown checkpoint' : 'All checkpoints'),
            'date' => $request->filled('date') ? $request->date : 'All dates',
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

        return sprintf('patrol-logs-%s-%s.pdf', $guardPart, now(config('app.timezone'))->format('Ymd-His'));
    }
}

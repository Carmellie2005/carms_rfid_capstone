<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\PatrolLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScanIssueController extends Controller
{
    private const ISSUE_STATUSES = [
        'invalid',
        'suspicious',
        'pending_face',
        'profile_incomplete',
        'outside_schedule',
        'expired',
    ];

    public function index(Request $request): View
    {
        $selectedStatus = in_array($request->input('status'), self::ISSUE_STATUSES, true)
            ? $request->input('status')
            : null;

        $issueQuery = $this->issueQuery();

        $scans = (clone $issueQuery)
            ->with(['securityGuard', 'checkpoint'])
            ->when($selectedStatus, fn (Builder $query) => $query->where('status', $selectedStatus))
            ->latest('scanned_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (PatrolLog $log) => [
                'log' => $log,
                'issueType' => $this->issueTypeFor($log),
                'suggestion' => $this->suggestionFor($log),
                'severity' => $this->severityFor($log),
            ]);

        return view('system.scan-issues.index', [
            'scans' => $scans,
            'selectedStatus' => $selectedStatus,
            'statusOptions' => $this->statusOptions(),
            'summary' => [
                'total' => (clone $issueQuery)->count(),
                'unregistered' => (clone $issueQuery)
                    ->where(fn (Builder $query) => $this->whereUnregisteredScan($query))
                    ->count(),
                'invalid' => (clone $issueQuery)->where('status', 'invalid')->count(),
                'needsFace' => (clone $issueQuery)->whereIn('status', ['pending_face', 'profile_incomplete'])->count(),
            ],
        ]);
    }

    private function issueQuery(): Builder
    {
        return PatrolLog::query()->whereIn('status', self::ISSUE_STATUSES);
    }

    private function whereUnregisteredScan(Builder $query): Builder
    {
        return $query
            ->whereNull('guard_id')
            ->orWhereDoesntHave('securityGuard')
            ->orWhereHas('securityGuard', fn (Builder $guardQuery) => $guardQuery->where('employee_no', 'UNKNOWN'))
            ->orWhere('notes', 'like', '%not assigned%');
    }

    private function statusOptions(): array
    {
        return collect(self::ISSUE_STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $this->labelFor($status)])
            ->all();
    }

    private function issueTypeFor(PatrolLog $log): string
    {
        $notes = strtolower((string) $log->notes);

        if ($this->isUnregisteredScan($log)) {
            return 'Unregistered RFID';
        }

        if (str_contains($notes, 'guard is inactive')) {
            return 'Inactive Guard';
        }

        if (str_contains($notes, 'not registered') || ! $log->checkpoint) {
            return 'Reader Setup';
        }

        return $this->labelFor($log->status);
    }

    private function isUnregisteredScan(PatrolLog $log): bool
    {
        return ! $log->securityGuard
            || $log->securityGuard->employee_no === 'UNKNOWN'
            || str_contains(strtolower((string) $log->notes), 'not assigned');
    }

    private function suggestionFor(PatrolLog $log): string
    {
        $notes = strtolower((string) $log->notes);

        if (str_contains($notes, 'not assigned')) {
            return 'Register this RFID UID to the correct guard profile.';
        }

        if (str_contains($notes, 'guard is inactive')) {
            return 'Activate the guard profile or assign the card to an active guard.';
        }

        if (str_contains($notes, 'not registered')) {
            return 'Check the ESP32 DEVICE_UID and register it in checkpoint settings.';
        }

        if (str_contains($notes, 'checkpoint is inactive')) {
            return 'Activate the checkpoint or assign the device to an active checkpoint.';
        }

        if ($log->status === 'profile_incomplete') {
            return 'Open the guard profile and complete live face registration.';
        }

        if ($log->status === 'pending_face') {
            return 'Ask the guard to open Scan Checkpoint and finish face verification.';
        }

        if ($log->status === 'outside_schedule') {
            return 'Patrol scans are accepted only during the scheduled patrol window.';
        }

        if ($log->status === 'expired') {
            return 'A newer RFID scan replaced this pending face verification.';
        }

        if ($log->status === 'suspicious') {
            return 'Review the face verification attempt and patrol details.';
        }

        return 'Review the guard RFID UID, checkpoint code, and reader device UID.';
    }

    private function severityFor(PatrolLog $log): string
    {
        return match ($log->status) {
            'invalid', 'suspicious' => 'danger',
            'profile_incomplete', 'pending_face', 'outside_schedule', 'expired' => 'warning',
            default => 'ok',
        };
    }

    private function labelFor(?string $value): string
    {
        return Str::of($value ?: 'unknown')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}

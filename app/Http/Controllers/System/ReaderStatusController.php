<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\PatrolLog;
use Illuminate\View\View;

class ReaderStatusController extends Controller
{
    public function index(): View
    {
        $onlineCutoff = now()->subMinutes(2);

        $checkpoints = Checkpoint::with(['latestPatrolLog.securityGuard'])
            ->orderBy('code')
            ->get()
            ->map(function (Checkpoint $checkpoint) use ($onlineCutoff) {
                $lastSeen = $checkpoint->reader_last_seen_at ?? $checkpoint->latestPatrolLog?->scanned_at;

                $checkpoint->reader_state = match (true) {
                    blank($checkpoint->device_uid) => 'no_device',
                    $checkpoint->status !== 'active' => 'inactive',
                    $lastSeen && $lastSeen->greaterThanOrEqualTo($onlineCutoff) => 'online',
                    default => 'offline',
                };
                $checkpoint->reader_seen_at = $lastSeen;
                $checkpoint->reader_has_device_issue = $this->hasDeviceIssue($checkpoint->reader_diagnostics ?? []);

                return $checkpoint;
            });

        $troubleScans = PatrolLog::whereIn('status', [
            'invalid',
            'suspicious',
            'profile_incomplete',
            'pending_selfie',
            'pending_face',
            'outside_schedule',
            'expired',
        ])->count();

        return view('system.readers.index', [
            'checkpoints' => $checkpoints,
            'summary' => [
                'total' => $checkpoints->count(),
                'online' => $checkpoints->where('reader_state', 'online')->count(),
                'offline' => $checkpoints->where('reader_state', 'offline')->count(),
                'needsSetup' => $checkpoints->whereIn('reader_state', ['no_device', 'inactive'])->count(),
                'deviceIssues' => $checkpoints->where('reader_has_device_issue', true)->count(),
                'troubleScans' => $troubleScans,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function hasDeviceIssue(array $diagnostics): bool
    {
        foreach (['wifi_status', 'rfid_status', 'lcd_status', 'buzzer_status'] as $field) {
            $status = strtolower((string) ($diagnostics[$field] ?? ''));

            if (in_array($status, ['offline', 'failed', 'error', 'disconnected'], true)) {
                return true;
            }
        }

        return filled($diagnostics['last_error'] ?? null);
    }
}

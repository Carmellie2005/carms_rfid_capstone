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
        $onlineCutoff = now()->subMinutes(5);

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

                return $checkpoint;
            });

        $recentScans = PatrolLog::with(['securityGuard', 'checkpoint'])
            ->latest('scanned_at')
            ->take(15)
            ->get()
            ->map(fn (PatrolLog $log) => [
                'log' => $log,
                'suggestion' => $this->suggestionFor($log),
                'severity' => $this->severityFor($log),
            ]);

        return view('system.readers.index', [
            'checkpoints' => $checkpoints,
            'recentScans' => $recentScans,
            'summary' => [
                'total' => $checkpoints->count(),
                'online' => $checkpoints->where('reader_state', 'online')->count(),
                'offline' => $checkpoints->where('reader_state', 'offline')->count(),
                'needsSetup' => $checkpoints->whereIn('reader_state', ['no_device', 'inactive'])->count(),
                'troubleScans' => $recentScans->whereIn('severity', ['warning', 'danger'])->count(),
            ],
        ]);
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

        if ($log->status === 'suspicious') {
            return 'Review the face verification attempt and patrol details.';
        }

        if ($log->status === 'valid') {
            return 'No action needed.';
        }

        return 'Review the guard RFID UID, checkpoint code, and reader device UID.';
    }

    private function severityFor(PatrolLog $log): string
    {
        return match ($log->status) {
            'invalid', 'suspicious' => 'danger',
            'profile_incomplete', 'pending_face', 'outside_schedule' => 'warning',
            default => 'ok',
        };
    }
}

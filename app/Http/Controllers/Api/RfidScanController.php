<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use App\Support\PatrolSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RfidScanController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('get') && ! $this->hasScanPayload($request)) {
            return response()->json([
                'message' => 'RFID endpoint is ready. Send rfid_uid and device_uid as query parameters or JSON fields.',
                'example_get' => url('/api/rfid-scan?rfid_uid=F33C8D37&device_uid=ESP32-IT-01'),
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => PatrolSchedule::isTestingMode(),
                'testing_notice' => PatrolSchedule::isTestingMode() ? PatrolSchedule::testingNotice() : null,
                'example_post' => [
                    'rfid_uid' => 'F33C8D37',
                    'device_uid' => 'ESP32-IT-01',
                ],
            ]);
        }

        $request->merge($this->normalizedHardwarePayload($request));

        $data = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:100'],
            'checkpoint_code' => ['nullable', 'string', 'max:100'],
            'device_uid' => ['nullable', 'string', 'max:100'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $rfidUid = strtoupper(trim($data['rfid_uid']));
        $checkpointToken = strtoupper(trim($data['checkpoint_code'] ?? ''));
        $checkpointToken = $checkpointToken !== ''
            ? $checkpointToken
            : strtoupper(trim($data['device_uid'] ?? ''));
        $scannedAt = filled($data['scanned_at'] ?? null)
            ? Carbon::parse($data['scanned_at'])->timezone(config('app.timezone'))
            : now(config('app.timezone'));

        $matchedGuard = Guard::with('faceDescriptors')
            ->where('rfid_uid', $rfidUid)
            ->first();
        $guard = $matchedGuard?->status === 'active' ? $matchedGuard : null;

        $matchedCheckpoint = Checkpoint::where(function ($query) use ($checkpointToken) {
            $query->where('code', $checkpointToken)->orWhere('device_uid', $checkpointToken);
        })->first();
        $checkpoint = $matchedCheckpoint?->status === 'active' ? $matchedCheckpoint : null;

        $isProfileIncomplete = $guard
            && $checkpoint
            && ! $this->hasCompletedFaceRegistration($guard);
        $isValid = $guard && $checkpoint && ! $isProfileIncomplete;
        $diagnostic = $this->scanDiagnostic($matchedGuard, $guard, $matchedCheckpoint, $checkpoint, $isProfileIncomplete, $isValid);

        if (! PatrolSchedule::isOpen()) {
            $scheduleMessage = PatrolSchedule::closedMessage();
            $patrolLog = PatrolLog::create([
                'guard_id' => $matchedGuard?->id ?? $this->unknownGuard()->id,
                'checkpoint_id' => $matchedCheckpoint?->id,
                'rfid_uid' => $rfidUid,
                'checkpoint_code' => $matchedCheckpoint?->code ?? $checkpointToken,
                'rfid_status' => 'outside_schedule',
                'facial_status' => 'not_started',
                'status' => 'outside_schedule',
                'scanned_at' => $scannedAt,
                'notes' => $scheduleMessage.' '.$diagnostic,
            ]);

            $this->markReaderSeen($matchedCheckpoint, $request, $patrolLog->status, $scheduleMessage);

            AuditLogger::record('rfid_scan_blocked_schedule', 'RFID scan blocked outside patrol schedule.', $patrolLog, [
                'rfid_uid' => $rfidUid,
                'checkpoint_token' => $checkpointToken,
                'device_uid' => $data['device_uid'] ?? null,
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => PatrolSchedule::isTestingMode(),
                'current_time' => PatrolSchedule::manilaTime()->toDateTimeString(),
                'diagnostic' => $diagnostic,
                'guard_id' => $matchedGuard?->id,
                'checkpoint_id' => $matchedCheckpoint?->id,
            ]);

            return response()->json([
                'message' => $scheduleMessage,
                'diagnostic' => $scheduleMessage,
                'patrol_log_id' => $patrolLog->id,
                'status' => $patrolLog->status,
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => PatrolSchedule::isTestingMode(),
                'testing_notice' => PatrolSchedule::isTestingMode() ? PatrolSchedule::testingNotice() : null,
                'guard' => $guard?->only(['id', 'name', 'employee_no']),
                'checkpoint' => $checkpoint?->only(['id', 'code', 'name', 'location']),
            ], 409);
        }

        if ($isValid) {
            PatrolLog::where('guard_id', $guard->id)
                ->where('rfid_status', 'valid')
                ->where('facial_status', 'pending')
                ->where('status', 'pending_face')
                ->update([
                    'facial_status' => 'expired',
                    'status' => 'expired',
                    'notes' => 'This pending face verification was replaced by a newer RFID checkpoint scan.',
                ]);
        }

        $patrolLog = PatrolLog::create([
            'guard_id' => $matchedGuard?->id ?? $this->unknownGuard()->id,
            'checkpoint_id' => $matchedCheckpoint?->id,
            'rfid_uid' => $rfidUid,
            'checkpoint_code' => $matchedCheckpoint?->code ?? $checkpointToken,
            'rfid_status' => ($isValid || $isProfileIncomplete) ? 'valid' : 'invalid',
            'facial_status' => $isValid ? 'pending' : 'not_started',
            'status' => match (true) {
                $isValid => 'pending_face',
                $isProfileIncomplete => 'profile_incomplete',
                default => 'invalid',
            },
            'scanned_at' => $scannedAt,
            'notes' => $diagnostic,
        ]);

        $this->markReaderSeen($matchedCheckpoint, $request, $patrolLog->status, $diagnostic);

        AuditLogger::record('rfid_scan_received', 'RFID scan received from checkpoint reader.', $patrolLog, [
            'rfid_uid' => $rfidUid,
            'checkpoint_token' => $checkpointToken,
            'device_uid' => $data['device_uid'] ?? null,
            'result' => $patrolLog->status,
            'diagnostic' => $diagnostic,
            'guard_id' => $matchedGuard?->id,
            'checkpoint_id' => $matchedCheckpoint?->id,
            'patrol_window' => PatrolSchedule::windowLabel(),
            'testing_mode' => PatrolSchedule::isTestingMode(),
        ]);

        return response()->json([
            'message' => match (true) {
                $isValid => 'RFID scan accepted. Facial verification required.',
                $isProfileIncomplete => 'Face registration is required before patrol verification.',
                default => 'RFID scan recorded as invalid.',
            },
            'diagnostic' => $diagnostic,
            'patrol_log_id' => $patrolLog->id,
            'status' => $patrolLog->status,
            'patrol_window' => PatrolSchedule::windowLabel(),
            'testing_mode' => PatrolSchedule::isTestingMode(),
            'testing_notice' => PatrolSchedule::isTestingMode() ? PatrolSchedule::testingNotice() : null,
            'guard' => $guard?->only(['id', 'name', 'employee_no']),
            'checkpoint' => $checkpoint?->only(['id', 'code', 'name', 'location']),
        ], match (true) {
            $isValid => 201,
            $isProfileIncomplete => 409,
            default => 422,
        });
    }

    private function hasScanPayload(Request $request): bool
    {
        return collect(['rfid_uid', 'uid', 'card_uid', 'card', 'rfid'])
            ->contains(fn ($key) => $request->filled($key));
    }

    private function normalizedHardwarePayload(Request $request): array
    {
        $payload = [];

        $rfidUid = $this->firstFilled($request, ['rfid_uid', 'uid', 'card_uid', 'card', 'rfid']);
        $checkpointCode = $this->firstFilled($request, ['checkpoint_code', 'checkpoint', 'checkpoint_uid', 'code']);
        $deviceUid = $this->firstFilled($request, ['device_uid', 'device', 'reader_uid', 'reader']);

        if ($rfidUid !== null) {
            $payload['rfid_uid'] = $rfidUid;
        }

        if ($checkpointCode !== null) {
            $payload['checkpoint_code'] = $checkpointCode;
        }

        if ($deviceUid !== null) {
            $payload['device_uid'] = $deviceUid;
        }

        return $payload;
    }

    private function firstFilled(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return (string) $request->input($key);
            }
        }

        return null;
    }

    private function scanDiagnostic(?Guard $matchedGuard, ?Guard $guard, ?Checkpoint $matchedCheckpoint, ?Checkpoint $checkpoint, bool $isProfileIncomplete, bool $isValid): string
    {
        if ($isValid) {
            return 'RFID accepted by hardware API; awaiting facial verification.';
        }

        if (! $matchedGuard) {
            return 'RFID card is not assigned to any guard profile.';
        }

        if (! $guard) {
            return 'RFID card belongs to a guard profile, but that guard is inactive.';
        }

        if (! $matchedCheckpoint) {
            return 'Reader device or checkpoint code is not registered in the system.';
        }

        if (! $checkpoint) {
            return 'Reader device is registered, but the checkpoint is inactive.';
        }

        if ($isProfileIncomplete) {
            return 'RFID accepted, but guard face registration must be completed before patrol verification.';
        }

        return 'RFID scan could not be accepted. Review guard, checkpoint, and device setup.';
    }

    private function markReaderSeen(?Checkpoint $checkpoint, Request $request, string $status, string $message): void
    {
        if (! $checkpoint) {
            return;
        }

        $checkpoint->update([
            'reader_last_seen_at' => now(config('app.timezone')),
            'reader_last_ip' => $request->ip(),
            'reader_last_status' => $status,
            'reader_last_message' => $message,
        ]);
    }

    private function hasCompletedFaceRegistration(Guard $guard): bool
    {
        return $guard->faceDescriptors->contains(
            fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128
        );
    }

    private function unknownGuard(): Guard
    {
        return Guard::firstOrCreate(
            ['employee_no' => 'UNKNOWN'],
            [
                'name' => 'Unregistered RFID Card',
                'rfid_uid' => 'UNKNOWN',
                'status' => 'inactive',
            ]
        );
    }
}

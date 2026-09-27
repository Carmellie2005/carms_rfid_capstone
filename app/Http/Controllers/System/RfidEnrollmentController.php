<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\RfidEnrollmentScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RfidEnrollmentController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'prepare' => ['nullable', 'boolean'],
        ]);

        $latestId = (int) (RfidEnrollmentScan::query()->max('id') ?? 0);

        if ($request->boolean('prepare')) {
            return response()->json([
                'rfid_uid' => null,
                'latest_id' => $latestId,
                'server_time' => now(config('app.timezone'))->toIso8601String(),
            ]);
        }

        $since = filled($data['since'] ?? null)
            ? Carbon::parse($data['since'])->timezone(config('app.timezone'))
            : null;

        $latest = RfidEnrollmentScan::query()
            ->when(
                array_key_exists('after_id', $data) && $data['after_id'] !== null,
                fn ($query) => $query->where('id', '>', (int) $data['after_id']),
                fn ($query) => $query->when($since, fn ($query) => $query->where('captured_at', '>=', $since))
            )
            ->latest('id')
            ->first();

        if (! $latest) {
            return response()->json([
                'rfid_uid' => null,
                'latest_id' => $latestId,
            ]);
        }

        $capturedAt = $latest->captured_at->timezone(config('app.timezone'));

        return response()->json([
            'id' => $latest->id,
            'rfid_uid' => $latest->rfid_uid,
            'device_uid' => $latest->device_uid,
            'captured_at' => $capturedAt->toIso8601String(),
            'latest_id' => max($latestId, $latest->id),
            'assigned_guard' => $this->assignedGuardPayload($latest->rfid_uid),
        ]);
    }

    private function assignedGuardPayload(string $rfidUid): ?array
    {
        $guard = Guard::query()
            ->where('rfid_uid', strtoupper(trim($rfidUid)))
            ->first(['id', 'employee_no', 'name']);

        if (! $guard) {
            return null;
        }

        return [
            'id' => $guard->id,
            'employee_no' => $guard->employee_no,
            'name' => $guard->name,
        ];
    }
}

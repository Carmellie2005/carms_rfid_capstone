<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
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
        ]);

        $since = filled($data['since'] ?? null)
            ? Carbon::parse($data['since'])->timezone(config('app.timezone'))
            : null;

        $latest = RfidEnrollmentScan::query()
            ->when($since, fn ($query) => $query->where('captured_at', '>=', $since))
            ->latest('captured_at')
            ->first();

        if (! $latest) {
            return response()->json(['rfid_uid' => null]);
        }

        $capturedAt = $latest->captured_at->timezone(config('app.timezone'));

        return response()->json([
            'rfid_uid' => $latest->rfid_uid,
            'device_uid' => $latest->device_uid,
            'captured_at' => $capturedAt->toIso8601String(),
        ]);
    }
}

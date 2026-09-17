<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Api\RfidEnrollmentController as ApiRfidEnrollmentController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RfidEnrollmentController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $latest = Cache::get(ApiRfidEnrollmentController::CACHE_KEY);

        if (! $latest) {
            return response()->json(['rfid_uid' => null]);
        }

        $capturedAt = Carbon::parse($latest['captured_at'])->timezone(config('app.timezone'));
        $since = filled($data['since'] ?? null)
            ? Carbon::parse($data['since'])->timezone(config('app.timezone'))
            : null;

        if ($since && $capturedAt->lt($since)) {
            return response()->json(['rfid_uid' => null]);
        }

        return response()->json([
            'rfid_uid' => $latest['rfid_uid'] ?? null,
            'device_uid' => $latest['device_uid'] ?? null,
            'captured_at' => $capturedAt->toIso8601String(),
        ]);
    }
}

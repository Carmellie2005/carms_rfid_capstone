<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfidEnrollmentScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidEnrollmentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('get') && ! $this->hasEnrollmentPayload($request)) {
            return response()->json([
                'message' => 'RFID enrollment endpoint is ready. Send rfid_uid for guard card registration.',
                'example_get' => url('/api/rfid-enrollment?rfid_uid=F33C8D37&device_uid=ENROLLMENT-READER'),
                'example_post' => [
                    'rfid_uid' => 'F33C8D37',
                    'device_uid' => 'ENROLLMENT-READER',
                ],
            ]);
        }

        $request->merge($this->normalizedHardwarePayload($request));

        $data = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:100'],
            'device_uid' => ['nullable', 'string', 'max:100'],
        ]);

        $capturedAt = now(config('app.timezone'));
        $rfidUid = strtoupper(trim($data['rfid_uid']));
        $deviceUid = filled($data['device_uid'] ?? null)
            ? strtoupper(trim($data['device_uid']))
            : null;

        RfidEnrollmentScan::create([
            'rfid_uid' => $rfidUid,
            'device_uid' => $deviceUid,
            'captured_at' => $capturedAt->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'RFID UID captured for guard enrollment.',
            'rfid_uid' => $rfidUid,
            'device_uid' => $deviceUid,
            'captured_at' => $capturedAt->toIso8601String(),
        ], 201);
    }

    private function hasEnrollmentPayload(Request $request): bool
    {
        return collect(['rfid_uid', 'uid', 'card_uid', 'card', 'rfid'])
            ->contains(fn ($key) => $request->filled($key));
    }

    private function normalizedHardwarePayload(Request $request): array
    {
        $payload = [];

        $rfidUid = $this->firstFilled($request, ['rfid_uid', 'uid', 'card_uid', 'card', 'rfid']);
        $deviceUid = $this->firstFilled($request, ['device_uid', 'device', 'reader_uid', 'reader']);

        if ($rfidUid !== null) {
            $payload['rfid_uid'] = $rfidUid;
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
}

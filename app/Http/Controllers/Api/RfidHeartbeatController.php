<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidHeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('get') && ! $this->hasHeartbeatPayload($request)) {
            return response()->json([
                'message' => 'RFID heartbeat endpoint is ready. Send device_uid or checkpoint_code as query parameters or JSON fields.',
                'example_get' => url('/api/rfid-heartbeat?device_uid=ESP32-IT-01'),
                'example_post' => [
                    'device_uid' => 'ESP32-IT-01',
                ],
            ]);
        }

        $request->merge($this->normalizedPayload($request));

        $data = $request->validate([
            'device_uid' => ['nullable', 'string', 'max:100'],
            'checkpoint_code' => ['nullable', 'string', 'max:100'],
        ]);

        $token = strtoupper(trim($data['checkpoint_code'] ?? ''));
        $token = $token !== '' ? $token : strtoupper(trim($data['device_uid'] ?? ''));

        $checkpoint = Checkpoint::where(function ($query) use ($token) {
            $query->where('code', $token)->orWhere('device_uid', $token);
        })->first();

        if (! $checkpoint) {
            return response()->json([
                'message' => 'Reader device is not registered.',
                'status' => 'not_registered',
            ], 422);
        }

        $status = $checkpoint->status === 'active' ? 'online' : 'inactive_checkpoint';
        $message = $checkpoint->status === 'active'
            ? 'Reader heartbeat received.'
            : 'Reader heartbeat received, but checkpoint is inactive.';

        $checkpoint->update([
            'reader_last_seen_at' => now(config('app.timezone')),
            'reader_last_ip' => $request->ip(),
            'reader_last_status' => $status,
            'reader_last_message' => $message,
        ]);

        return response()->json([
            'message' => $message,
            'status' => $status,
            'checkpoint' => $checkpoint->only(['id', 'code', 'name', 'location', 'device_uid']),
        ], $checkpoint->status === 'active' ? 200 : 409);
    }

    private function hasHeartbeatPayload(Request $request): bool
    {
        return collect(['device_uid', 'device', 'reader_uid', 'reader', 'checkpoint_code', 'checkpoint', 'code'])
            ->contains(fn ($key) => $request->filled($key));
    }

    private function normalizedPayload(Request $request): array
    {
        $payload = [];
        $deviceUid = $this->firstFilled($request, ['device_uid', 'device', 'reader_uid', 'reader']);
        $checkpointCode = $this->firstFilled($request, ['checkpoint_code', 'checkpoint', 'code']);

        if ($deviceUid !== null) {
            $payload['device_uid'] = $deviceUid;
        }

        if ($checkpointCode !== null) {
            $payload['checkpoint_code'] = $checkpointCode;
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

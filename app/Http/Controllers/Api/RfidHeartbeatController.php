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
                    'wifi_status' => 'connected',
                    'wifi_rssi' => -55,
                    'local_ip' => '192.168.1.50',
                    'rfid_status' => 'online',
                    'lcd_status' => 'online',
                    'buzzer_status' => 'ready',
                ],
            ]);
        }

        $request->merge($this->normalizedPayload($request));

        $data = $request->validate([
            'device_uid' => ['nullable', 'string', 'max:100'],
            'checkpoint_code' => ['nullable', 'string', 'max:100'],
            'wifi_status' => ['nullable', 'string', 'max:50'],
            'wifi_rssi' => ['nullable', 'integer', 'between:-120,50'],
            'local_ip' => ['nullable', 'string', 'max:45'],
            'rfid_status' => ['nullable', 'string', 'max:50'],
            'lcd_status' => ['nullable', 'string', 'max:50'],
            'buzzer_status' => ['nullable', 'string', 'max:50'],
            'firmware' => ['nullable', 'string', 'max:100'],
            'last_error' => ['nullable', 'string', 'max:255'],
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

        $diagnostics = $this->diagnosticsFrom($data);

        $updates = [
            'reader_last_seen_at' => now(config('app.timezone')),
            'reader_last_ip' => $request->ip(),
            'reader_last_status' => $status,
            'reader_last_message' => $message,
        ];

        if ($diagnostics !== []) {
            $updates['reader_diagnostics'] = $diagnostics;
        }

        $checkpoint->update($updates);

        return response()->json([
            'message' => $message,
            'status' => $status,
            'checkpoint' => $checkpoint->only(['id', 'code', 'name', 'location', 'device_uid']),
        ], $checkpoint->status === 'active' ? 200 : 409);
    }

    private function hasHeartbeatPayload(Request $request): bool
    {
        return collect([
            'device_uid',
            'device',
            'reader_uid',
            'reader',
            'checkpoint_code',
            'checkpoint',
            'code',
            'wifi_status',
            'rfid_status',
            'lcd_status',
            'buzzer_status',
        ])
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function diagnosticsFrom(array $data): array
    {
        $diagnostics = [];

        foreach ([
            'wifi_status',
            'wifi_rssi',
            'local_ip',
            'rfid_status',
            'lcd_status',
            'buzzer_status',
            'firmware',
            'last_error',
        ] as $key) {
            if (array_key_exists($key, $data) && filled($data[$key])) {
                $diagnostics[$key] = $data[$key];
            }
        }

        return $diagnostics;
    }
}

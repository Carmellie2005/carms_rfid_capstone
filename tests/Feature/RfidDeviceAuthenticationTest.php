<?php

namespace Tests\Feature;

use Tests\TestCase;

class RfidDeviceAuthenticationTest extends TestCase
{
    public function test_rfid_api_rejects_missing_device_token(): void
    {
        $this
            ->postJson(route('api.rfid-scan'), [
                'rfid_uid' => 'F33C8D37',
                'device_uid' => 'ESP32-IT-01',
            ])
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'RFID device token is missing or invalid.',
            ]);
    }

    public function test_rfid_api_rejects_invalid_device_token(): void
    {
        $this
            ->withRfidDeviceToken('wrong-token')
            ->postJson(route('api.rfid-heartbeat'), [
                'device_uid' => 'ESP32-IT-01',
            ])
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'RFID device token is missing or invalid.',
            ]);
    }

    public function test_rfid_api_accepts_x_device_token_header(): void
    {
        $this
            ->withHeader('X-Device-Token', self::RFID_DEVICE_TOKEN)
            ->getJson(route('api.rfid-heartbeat'))
            ->assertOk()
            ->assertJson([
                'message' => 'RFID heartbeat endpoint is ready. Send device_uid or checkpoint_code as query parameters or JSON fields.',
            ]);
    }
}

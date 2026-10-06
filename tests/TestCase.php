<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected const RFID_DEVICE_TOKEN = 'test-rfid-device-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.rfid.device_token' => self::RFID_DEVICE_TOKEN]);
    }

    protected function withRfidDeviceToken(?string $token = null): static
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? self::RFID_DEVICE_TOKEN));
    }
}

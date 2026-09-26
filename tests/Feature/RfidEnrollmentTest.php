<?php

namespace Tests\Feature;

use App\Models\PatrolLog;
use App\Models\RfidEnrollmentScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_reader_captures_uid_without_creating_patrol_log(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $this
            ->postJson(route('api.rfid-enrollment'), [
                'rfid_uid' => 'f33c8d37',
                'device_uid' => 'enrollment-reader',
            ])
            ->assertCreated()
            ->assertJson([
                'rfid_uid' => 'F33C8D37',
                'device_uid' => 'ENROLLMENT-READER',
            ]);

        $this->assertSame(0, PatrolLog::count());
        $this->assertSame('F33C8D37', RfidEnrollmentScan::first()?->rfid_uid);

        $this
            ->actingAs($supervisor)
            ->getJson(route('guards.rfid-enrollment.latest', [
                'since' => now()->subMinute()->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJson([
                'rfid_uid' => 'F33C8D37',
                'device_uid' => 'ENROLLMENT-READER',
            ]);
    }

    public function test_only_supervisors_can_read_latest_enrollment_uid(): void
    {
        $guard = User::factory()->create([
            'role' => 'guard',
            'username' => 'guard.user',
        ]);

        $this
            ->actingAs($guard)
            ->getJson(route('guards.rfid-enrollment.latest'))
            ->assertForbidden();
    }
}

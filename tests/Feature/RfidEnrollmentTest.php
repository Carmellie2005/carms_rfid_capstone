<?php

namespace Tests\Feature;

use App\Models\Guard;
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
            ->withRfidDeviceToken()
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

    public function test_supervisor_can_prepare_and_read_only_newer_enrollment_scans(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $oldScan = RfidEnrollmentScan::create([
            'rfid_uid' => 'OLD12345',
            'device_uid' => 'ESP32-REG-01',
            'captured_at' => now()->subMinute(),
        ]);

        $prepareResponse = $this
            ->actingAs($supervisor)
            ->getJson(route('guards.rfid-enrollment.latest', [
                'prepare' => true,
            ]))
            ->assertOk()
            ->assertJson([
                'rfid_uid' => null,
                'latest_id' => $oldScan->id,
            ]);

        RfidEnrollmentScan::create([
            'rfid_uid' => '8049C4CC',
            'device_uid' => 'ESP32-REG-01',
            'captured_at' => now(),
        ]);

        $this
            ->actingAs($supervisor)
            ->getJson(route('guards.rfid-enrollment.latest', [
                'after_id' => $prepareResponse->json('latest_id'),
            ]))
            ->assertOk()
            ->assertJson([
                'rfid_uid' => '8049C4CC',
                'device_uid' => 'ESP32-REG-01',
            ]);
    }

    public function test_latest_enrollment_scan_reports_assigned_guard(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'assigned.guard',
        ]);

        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-ASSIGNED',
            'name' => 'Assigned Guard',
            'email' => 'assigned.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => '8049C4CC',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        RfidEnrollmentScan::create([
            'rfid_uid' => '8049C4CC',
            'device_uid' => 'ESP32-REG-01',
            'captured_at' => now(),
        ]);

        $this
            ->actingAs($supervisor)
            ->getJson(route('guards.rfid-enrollment.latest'))
            ->assertOk()
            ->assertJsonPath('assigned_guard.id', $guard->id)
            ->assertJsonPath('assigned_guard.employee_no', 'SG-ASSIGNED')
            ->assertJsonPath('assigned_guard.name', 'Assigned Guard');
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

<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_greets_the_signed_in_supervisor_by_name(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Security Supervisor',
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Welcome, Security Supervisor')
            ->assertDontSee('Command Dashboard');
    }

    public function test_dashboard_recent_patrol_logs_hide_expired_scans(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Security Supervisor',
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guardUser = User::factory()->create(['role' => 'guard']);
        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-DASH',
            'name' => 'Dashboard Guard',
            'email' => 'dashboard.guard@example.com',
            'rfid_uid' => 'RFID-DASH',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-DASH',
            'name' => 'Visible Dashboard Checkpoint',
            'location' => 'Campus',
            'device_uid' => 'ESP32-DASH',
            'status' => 'active',
        ]);

        $expiredCheckpoint = Checkpoint::create([
            'code' => 'CP-EXPIRED-DASH',
            'name' => 'Expired Dashboard Checkpoint',
            'location' => 'Campus',
            'device_uid' => 'ESP32-EXPIRED-DASH',
            'status' => 'active',
        ]);

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-DASH',
            'checkpoint_code' => 'CP-DASH',
            'rfid_status' => 'valid',
            'facial_status' => 'verified',
            'status' => 'valid',
            'scanned_at' => now(),
        ]);

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $expiredCheckpoint->id,
            'rfid_uid' => 'RFID-EXPIRED-DASH',
            'checkpoint_code' => 'CP-EXPIRED-DASH',
            'rfid_status' => 'expired',
            'facial_status' => 'expired',
            'status' => 'expired',
            'scanned_at' => now()->addMinute(),
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Visible Dashboard Checkpoint')
            ->assertDontSee('Expired Dashboard Checkpoint');
    }
}

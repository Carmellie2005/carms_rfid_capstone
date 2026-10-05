<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_live_system_records(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Security Supervisor',
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guardUser = User::factory()->create([
            'name' => 'Viewer Guard Account',
            'role' => 'guard',
            'username' => 'viewer.guard',
            'email' => 'viewer.guard@example.com',
        ]);

        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-VIEW',
            'name' => 'Viewer Guard',
            'email' => 'viewer.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => 'RFID-VIEW',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-VIEW',
            'name' => 'Viewer Checkpoint',
            'location' => 'Main Gate',
            'device_uid' => 'ESP32-VIEW',
            'status' => 'active',
        ]);

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => $guard->rfid_uid,
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'status' => 'valid',
            'scanned_at' => now(),
        ]);

        IncidentReport::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => 'Facility Issue',
            'incident_type' => 'Facility Issue',
            'category' => 'Facility Issue',
            'priority' => 'normal',
            'severity' => 'medium',
            'location' => 'Main Gate',
            'incident_at' => now(),
            'reported_at' => now(),
            'description' => 'A light needs checking.',
            'status' => 'submitted',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('data-viewer.index'));

        $response
            ->assertOk()
            ->assertSee('Data Viewer')
            ->assertSee('Viewer Guard')
            ->assertSee('Viewer Checkpoint')
            ->assertSee('RFID UID masked')
            ->assertSee('Patrol Logs')
            ->assertDontSee('RFID-VIEW')
            ->assertDontSee('viewer.guard@example.com')
            ->assertDontSee('password');
    }

    public function test_guard_cannot_open_data_viewer(): void
    {
        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'regular.guard',
        ]);

        $this
            ->actingAs($guardUser)
            ->get(route('data-viewer.index'))
            ->assertForbidden();
    }
}

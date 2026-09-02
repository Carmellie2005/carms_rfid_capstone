<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_unregistered_scan_issues(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $unknownGuard = Guard::create([
            'employee_no' => 'UNKNOWN',
            'name' => 'Unregistered RFID Card',
            'rfid_uid' => 'UNKNOWN',
            'status' => 'inactive',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-ISSUE',
            'name' => 'Issue Checkpoint',
            'location' => 'Campus',
            'device_uid' => 'ESP32-ISSUE',
            'status' => 'active',
        ]);

        PatrolLog::create([
            'guard_id' => $unknownGuard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-UNREGISTERED',
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'invalid',
            'facial_status' => 'not_started',
            'status' => 'invalid',
            'scanned_at' => now(),
            'notes' => 'RFID card is not assigned to any guard profile.',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('scan-issues.index'));

        $response
            ->assertOk()
            ->assertSee('Scan Issues')
            ->assertSee('RFID-UNREGISTERED')
            ->assertSee('Unregistered RFID')
            ->assertSee('Register this RFID UID to the correct guard profile.')
            ->assertSee('Scan Issues');
    }

    public function test_guard_cannot_view_scan_issues(): void
    {
        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'regular.guard',
        ]);

        $this
            ->actingAs($guardUser)
            ->get(route('scan-issues.index'))
            ->assertForbidden();
    }
}

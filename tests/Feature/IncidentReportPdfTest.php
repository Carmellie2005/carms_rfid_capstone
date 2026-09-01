<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_download_incident_report_pdf(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $incident = $this->createIncidentReport();

        $response = $this
            ->actingAs($supervisor)
            ->get(route('incidents.pdf', $incident));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('incident-report-security-concern', $response->headers->get('content-disposition'));
    }

    public function test_guard_can_download_own_incident_report_pdf(): void
    {
        $guardUser = User::factory()->create(['role' => 'guard']);
        $guard = $this->createGuard($guardUser, 'G-001');
        $incident = $this->createIncidentReport($guard);

        $response = $this
            ->actingAs($guardUser)
            ->get(route('incidents.pdf', $incident));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_guard_cannot_download_another_guards_incident_report_pdf(): void
    {
        $ownerUser = User::factory()->create(['role' => 'guard']);
        $ownerGuard = $this->createGuard($ownerUser, 'G-001');
        $incident = $this->createIncidentReport($ownerGuard);

        $otherUser = User::factory()->create(['role' => 'guard']);
        $this->createGuard($otherUser, 'G-002');

        $this
            ->actingAs($otherUser)
            ->get(route('incidents.pdf', $incident))
            ->assertForbidden();
    }

    private function createIncidentReport(?Guard $guard = null): IncidentReport
    {
        $guard ??= $this->createGuard(User::factory()->create(['role' => 'guard']), 'G-001');

        $checkpoint = Checkpoint::create([
            'code' => 'CP-001',
            'name' => 'Main Gate',
            'location' => 'Campus Entrance',
            'device_uid' => 'DEVICE-001',
            'status' => 'active',
        ]);

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => $guard->rfid_uid,
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'facial_status' => 'verified',
            'status' => 'valid',
            'scanned_at' => now(),
        ]);

        return IncidentReport::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => 'Security Concern',
            'incident_type' => 'Security Concern',
            'category' => 'Security Concern',
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => now(),
            'reported_at' => now(),
            'description' => 'Suspicious activity was observed during patrol.',
            'status' => 'submitted',
        ]);
    }

    private function createGuard(User $user, string $employeeNo): Guard
    {
        return Guard::create([
            'user_id' => $user->id,
            'employee_no' => $employeeNo,
            'name' => $user->name,
            'email' => $user->email,
            'rfid_uid' => 'RFID-'.$employeeNo,
            'shift' => 'Day Shift',
            'status' => 'active',
        ]);
    }
}

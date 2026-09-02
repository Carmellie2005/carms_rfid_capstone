<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_filter_audit_trail_by_guard(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard();
        $otherGuard = $this->createGuard('SG-OTHER', 'RFID-OTHER');

        AuditLog::create([
            'user_id' => $guard->user_id,
            'actor_name' => $guard->name,
            'action' => 'patrol_completed',
            'description' => 'Selected guard patrol record.',
            'subject_type' => Guard::class,
            'subject_id' => $guard->id,
            'properties' => ['guard_id' => $guard->id],
        ]);

        AuditLog::create([
            'user_id' => $otherGuard->user_id,
            'actor_name' => $otherGuard->name,
            'action' => 'patrol_completed',
            'description' => 'Other guard patrol record.',
            'subject_type' => Guard::class,
            'subject_id' => $otherGuard->id,
            'properties' => ['guard_id' => $otherGuard->id],
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('audit-logs.index', ['guard_id' => $guard->id]));

        $response
            ->assertOk()
            ->assertSee('Selected guard patrol record.')
            ->assertDontSee('Other guard patrol record.')
            ->assertSee('border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700', false)
            ->assertSee('Download PDF')
            ->assertSee('Print PDF');
    }

    public function test_supervisor_can_download_guard_audit_trail_pdf(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-PDF', 'RFID-PDF');

        AuditLog::create([
            'user_id' => $guard->user_id,
            'actor_name' => $guard->name,
            'action' => 'rfid_scan_received',
            'description' => 'RFID scan received for PDF report.',
            'subject_type' => Guard::class,
            'subject_id' => $guard->id,
            'properties' => ['guard_id' => $guard->id, 'employee_no' => $guard->employee_no],
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('audit-logs.pdf', ['guard_id' => $guard->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('audit-trail-sg-pdf', $response->headers->get('content-disposition'));
    }

    public function test_guard_cannot_download_audit_trail_pdf(): void
    {
        $guard = $this->createGuard('SG-NO-PDF', 'RFID-NO-PDF');

        $this
            ->actingAs($guard->user)
            ->get(route('audit-logs.pdf', ['guard_id' => $guard->id]))
            ->assertForbidden();
    }

    private function createGuard(string $employeeNo = 'SG-AUDIT', string $rfidUid = 'RFID-AUDIT'): Guard
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => strtolower($employeeNo),
        ]);

        return Guard::create([
            'user_id' => $user->id,
            'employee_no' => $employeeNo,
            'name' => $user->name,
            'email' => $user->email,
            'rfid_uid' => $rfidUid,
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);
    }
}

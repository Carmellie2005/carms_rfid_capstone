<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\DutyRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            'status' => 'valid',
            'scanned_at' => now(),
        ]);

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $expiredCheckpoint->id,
            'rfid_uid' => 'RFID-EXPIRED-DASH',
            'checkpoint_code' => 'CP-EXPIRED-DASH',
            'rfid_status' => 'expired',
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

    public function test_dashboard_normal_priority_badge_is_distinct_from_submitted_status(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Security Supervisor',
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guardUser = User::factory()->create(['role' => 'guard']);
        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-INCIDENT-COLOR',
            'name' => 'Incident Color Guard',
            'email' => 'incident.color.guard@example.com',
            'rfid_uid' => 'RFID-INCIDENT-COLOR',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-INCIDENT-COLOR',
            'name' => 'Incident Color Checkpoint',
            'location' => 'Campus',
            'device_uid' => 'ESP32-INCIDENT-COLOR',
            'status' => 'active',
        ]);

        IncidentReport::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => 'Damaged Facility',
            'incident_type' => 'Damaged Facility',
            'category' => 'Damaged Facility',
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => now(),
            'reported_at' => now(),
            'description' => 'Window damage was reported.',
            'status' => 'submitted',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('bg-slate-50 text-slate-700 ring-slate-200', false)
            ->assertSee('bg-blue-50 text-blue-700 ring-blue-200', false)
            ->assertSee('Medium')
            ->assertSee('Submitted');
    }

    public function test_dashboard_does_not_show_scan_status_card(): void
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
            ->assertDontSee('Scan Status')
            ->assertDontSee('Valid, suspicious, invalid, pending selfie, and profile incomplete scans')
            ->assertDontSee('outside schedule scans')
            ->assertDontSee('Outside Schedule');
    }

    public function test_dashboard_shows_rotating_duty_and_fixed_evening_guard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 08:00:00', config('app.timezone')));

        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        foreach ([
            ['employee_no' => 'BCP-001', 'name' => 'Morning One'],
            ['employee_no' => 'BCP-002', 'name' => 'Morning Two'],
            ['employee_no' => 'BCP-003', 'name' => 'Morning Three'],
            ['employee_no' => 'BCP-004', 'name' => 'Evening Guard'],
        ] as $guardData) {
            Guard::create([
                ...$guardData,
                'email' => strtolower($guardData['employee_no']).'@example.com',
                'rfid_uid' => 'RFID-'.$guardData['employee_no'],
                'shift' => '24-hour duty',
                'status' => 'active',
            ]);
        }

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Duty Today')
            ->assertSee('BCP-002')
            ->assertSee('BCP-004')
            ->assertSee('24-hour duty')
            ->assertSee('Evening duty')
            ->assertSee('Off duty today: BCP-001, BCP-003');

        Carbon::setTestNow();
    }

    public function test_duty_roster_marks_guard_active_after_a_patrol_scan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 08:00:00', config('app.timezone')));

        $guard = Guard::create([
            'employee_no' => 'BCP-002',
            'name' => 'Morning Two',
            'email' => 'morning.two@example.com',
            'rfid_uid' => 'RFID-BCP-002',
            'shift' => '24-hour duty',
            'status' => 'active',
        ]);

        $guard->patrolLogs()->create([
            'rfid_uid' => $guard->rfid_uid,
            'checkpoint_code' => 'CP-TEST',
            'rfid_status' => 'valid',
            'status' => 'valid',
            'scanned_at' => now(),
        ]);

        $guards = Guard::query()
            ->where('status', 'active')
            ->withCount([
                'patrolLogs as today_patrols_count' => fn ($query) => $query
                    ->whereDate('scanned_at', now(config('app.timezone'))->toDateString())
                    ->where('status', '!=', 'expired'),
            ])
            ->get();
        $onDuty = DutyRoster::onDutyForDate(now(config('app.timezone')), $guards)->first();

        $this->assertSame('active', $onDuty?->duty_state);

        Carbon::setTestNow();
    }
}

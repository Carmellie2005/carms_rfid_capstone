<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_system_reports_have_separate_patrol_and_incident_pagination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 21:30:00', config('app.timezone')));

        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-REPORT', 'RFID-REPORT');
        $checkpoint = $this->createCheckpoint('CP-REPORT');

        foreach (range(1, 12) as $number) {
            PatrolLog::create([
                'guard_id' => $guard->id,
                'rfid_uid' => $guard->rfid_uid,
                'checkpoint_code' => sprintf('CP-REPORT-%02d', $number),
                'rfid_status' => 'valid',
                'facial_status' => 'verified',
                'status' => 'valid',
                'scanned_at' => Carbon::parse('2026-09-02 20:00:00', config('app.timezone'))->addMinutes($number),
            ]);
        }

        foreach (range(1, 7) as $number) {
            $this->createIncidentReport($guard, $checkpoint, $number);
        }

        $response = $this
            ->actingAs($supervisor)
            ->get(route('reports.index'));

        $response
            ->assertOk()
            ->assertSeeText('Showing 1 to 10 of 12 patrol report records')
            ->assertSeeText('Patrol page 1 of 2')
            ->assertSee('CP-REPORT-12')
            ->assertDontSee('CP-REPORT-01')
            ->assertSeeText('Showing 1 to 5 of 7 incident report records')
            ->assertSeeText('Incident page 1 of 2')
            ->assertSee('Report Incident 07');

        $response = $this
            ->actingAs($supervisor)
            ->get(route('reports.index', ['patrol_page' => 2, 'incident_page' => 2]));

        $response
            ->assertOk()
            ->assertSeeText('Showing 11 to 12 of 12 patrol report records')
            ->assertSeeText('Patrol page 2 of 2')
            ->assertSee('CP-REPORT-01')
            ->assertDontSee('CP-REPORT-12')
            ->assertSeeText('Showing 6 to 7 of 7 incident report records')
            ->assertSeeText('Incident page 2 of 2')
            ->assertSee('Report Incident 01');
    }

    public function test_incident_reports_page_uses_compact_pagination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 21:30:00', config('app.timezone')));

        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-INCIDENTS', 'RFID-INCIDENTS');
        $checkpoint = $this->createCheckpoint('CP-INCIDENTS');

        foreach (range(1, 6) as $number) {
            $this->createIncidentReport($guard, $checkpoint, $number);
        }

        $response = $this
            ->actingAs($supervisor)
            ->get(route('incidents.index'));

        $response
            ->assertOk()
            ->assertSeeText('Showing 1 to 5 of 6 incident reports')
            ->assertSeeText('Incident reports page 1 of 2')
            ->assertSee('Report Incident 06');

        $response = $this
            ->actingAs($supervisor)
            ->get(route('incidents.index', ['page' => 2]));

        $response
            ->assertOk()
            ->assertSeeText('Showing 6 to 6 of 6 incident reports')
            ->assertSeeText('Incident reports page 2 of 2')
            ->assertSee('Report Incident 01');
    }

    private function createGuard(string $employeeNo, string $rfidUid): Guard
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

    private function createCheckpoint(string $code): Checkpoint
    {
        return Checkpoint::create([
            'code' => $code,
            'name' => 'Checkpoint '.$code,
            'location' => 'Campus',
            'device_uid' => 'ESP32-'.$code,
            'status' => 'active',
        ]);
    }

    private function createIncidentReport(Guard $guard, Checkpoint $checkpoint, int $number): IncidentReport
    {
        return IncidentReport::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => sprintf('Report Incident %02d', $number),
            'incident_type' => sprintf('Report Incident %02d', $number),
            'category' => sprintf('Report Incident %02d', $number),
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => Carbon::parse('2026-09-02 19:00:00', config('app.timezone'))->addMinutes($number),
            'reported_at' => Carbon::parse('2026-09-02 19:00:00', config('app.timezone'))->addMinutes($number),
            'description' => sprintf('Report pagination description %02d.', $number),
            'status' => 'submitted',
        ]);
    }
}

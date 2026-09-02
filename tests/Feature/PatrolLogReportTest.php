<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatrolLogReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_download_patrol_logs_pdf(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-PDF-1', 'RFID-PDF-1');
        $this->createPatrolLog($guard);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('patrol-logs.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('patrol-logs-all-guards', $response->headers->get('content-disposition'));
    }

    public function test_guard_patrol_logs_page_has_date_filter_and_pdf_actions(): void
    {
        $guard = $this->createGuard('SG-FILTER', 'RFID-FILTER');

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index'));

        $response
            ->assertOk()
            ->assertSee('Date Filter')
            ->assertSee('Download PDF')
            ->assertSee('Print PDF');
    }

    public function test_guard_pdf_export_only_includes_own_patrol_logs(): void
    {
        $guard = $this->createGuard('SG-OWN', 'RFID-OWN');
        $otherGuard = $this->createGuard('SG-OTHER', 'RFID-OTHER');

        $this->createPatrolLog($guard);
        $this->createPatrolLog($otherGuard);

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.pdf', ['guard_id' => $otherGuard->id]));

        $response->assertOk();

        $export = AuditLog::where('action', 'patrol_logs_exported')->firstOrFail();

        $this->assertSame($guard->id, $export->subject_id);
        $this->assertSame(1, $export->properties['record_count']);
    }

    public function test_my_patrol_logs_show_and_filter_manila_scan_time(): void
    {
        $guard = $this->createGuard('SG-MNL', 'RFID-MNL');

        $this->createPatrolLog(
            $guard,
            Carbon::parse('2026-08-30 22:15:00', config('app.timezone')),
            'CP-MNL-TODAY',
        );
        $this->createPatrolLog(
            $guard,
            Carbon::parse('2026-08-29 22:15:00', config('app.timezone')),
            'CP-MNL-OLD',
        );

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index', ['date' => '2026-08-30']));

        $response
            ->assertOk()
            ->assertSee('Aug 30, 2026')
            ->assertSee('10:15 PM')
            ->assertSee('CP-MNL-TODAY')
            ->assertDontSee('Aug 29, 2026');
    }

    public function test_my_patrol_logs_are_paginated_for_guard(): void
    {
        $guard = $this->createGuard('SG-PAGE', 'RFID-PAGE');

        foreach (range(1, 7) as $number) {
            $this->createPatrolLog(
                $guard,
                Carbon::parse('2026-09-02 20:00:00', config('app.timezone'))->addMinutes($number),
                sprintf('CP-PAGE-%02d', $number),
            );
        }

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index'));

        $response
            ->assertOk()
            ->assertSeeText('Showing 1 to 6 of 7 my patrol logs')
            ->assertSeeText('Page 1 of 2')
            ->assertSee('CP-PAGE-07')
            ->assertDontSee('08:01 PM');

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index', ['page' => 2]));

        $response
            ->assertOk()
            ->assertSeeText('Showing 7 to 7 of 7 my patrol logs')
            ->assertSeeText('Page 2 of 2')
            ->assertSee('CP-PAGE-01')
            ->assertDontSee('08:07 PM');
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

    private function createPatrolLog(Guard $guard, ?Carbon $scannedAt = null, ?string $checkpointCode = null): PatrolLog
    {
        $checkpointCode ??= 'CP-'.$guard->employee_no;

        $checkpoint = Checkpoint::create([
            'code' => $checkpointCode,
            'name' => 'Checkpoint '.$guard->employee_no,
            'location' => 'Campus',
            'device_uid' => 'ESP32-'.$checkpointCode,
            'status' => 'active',
        ]);

        return PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => $guard->rfid_uid,
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'facial_status' => 'verified',
            'status' => 'valid',
            'scanned_at' => $scannedAt ?? now(config('app.timezone')),
        ]);
    }
}

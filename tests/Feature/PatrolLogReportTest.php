<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\PatrolChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatrolLogReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_patrol_logs_page_has_date_filter_and_pdf_actions(): void
    {
        $guard = $this->createGuard('SG-FILTER', 'RFID-FILTER');
        $patrolLog = $this->createPatrolLog($guard);

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index'));

        $response
            ->assertOk()
            ->assertSee('Date Filter')
            ->assertSee('Download PDF')
            ->assertSee('Print PDF')
            ->assertSee('/patrol-logs/'.$patrolLog->id.'/pdf', false);
    }

    public function test_supervisor_can_download_patrol_log_pdf(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-PDF', 'RFID-PDF');
        $patrolLog = $this->createPatrolLog($guard);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('patrol-logs.pdf', $patrolLog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('patrol-log-cp-sg-pdf', $response->headers->get('content-disposition'));
    }

    public function test_supervisor_can_preview_patrol_log_pdf_inline(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = $this->createGuard('SG-PREVIEW', 'RFID-PREVIEW');
        $patrolLog = $this->createPatrolLog($guard);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('patrol-logs.pdf', ['patrolLog' => $patrolLog, 'preview' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_guard_can_download_own_patrol_log_pdf(): void
    {
        $guard = $this->createGuard('SG-OWN', 'RFID-OWN');
        $patrolLog = $this->createPatrolLog($guard);

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.pdf', $patrolLog));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_guard_cannot_download_another_guards_patrol_log_pdf(): void
    {
        $owner = $this->createGuard('SG-OWNER', 'RFID-OWNER');
        $patrolLog = $this->createPatrolLog($owner);
        $otherGuard = $this->createGuard('SG-OTHER', 'RFID-OTHER');

        $this
            ->actingAs($otherGuard->user)
            ->get(route('patrol-logs.pdf', $patrolLog))
            ->assertForbidden();
    }

    public function test_patrol_logs_page_opens_checklist_and_proof_photos_from_details_button(): void
    {
        $guard = $this->createGuard('SG-PROOF', 'RFID-PROOF');
        $patrolLog = $this->createPatrolLog($guard);
        $checklist = $patrolLog->checklistResponse()->create([
            'item_statuses' => [
                'doors_locked' => PatrolChecklist::STATUS_NORMAL,
                'lighting_ok' => PatrolChecklist::STATUS_ISSUE,
                'cctv_alarm_checked' => PatrolChecklist::STATUS_NORMAL,
                'no_unauthorized_person' => PatrolChecklist::STATUS_NORMAL,
                'safety_hazard' => PatrolChecklist::STATUS_NORMAL,
            ],
        ]);
        $proofPhoto = $checklist->proofPhotos()->create([
            'patrol_log_id' => $patrolLog->id,
            'item_key' => 'lighting_ok',
            'item_label' => 'Lighting and visibility checked',
            'image_path' => 'checklist-proof-photos/missing.jpg',
            'mime_type' => 'image/jpeg',
            'image_data' => base64_encode('proof-photo'),
            'sort_order' => 1,
        ]);
        $proofPhotoUrl = route('patrol-logs.proof-photos.show', [$patrolLog, $proofPhoto]);
        $escapedProofPhotoUrl = str_replace('/', '\\/', $proofPhotoUrl);

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index'));

        $response
            ->assertOk()
            ->assertSee('View')
            ->assertSee('View patrol details', false)
            ->assertSee('openPatrolDetails', false)
            ->assertSee('1 issue found')
            ->assertSee('Patrol Photos')
            ->assertSee($escapedProofPhotoUrl, false)
            ->assertSee('openProofPhoto', false);

        $this
            ->actingAs($guard->user)
            ->get($proofPhotoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertContent('proof-photo');
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

    public function test_patrol_logs_hide_pending_selfie_records(): void
    {
        $guard = $this->createGuard('SG-HIDE', 'RFID-HIDE');

        $visibleLog = $this->createPatrolLog(
            $guard,
            Carbon::parse('2026-09-03 20:00:00', config('app.timezone')),
            'CP-HIDE-VALID',
        );

        $pendingSelfieLog = $this->createPatrolLog(
            $guard,
            Carbon::parse('2026-09-03 20:05:00', config('app.timezone')),
            'CP-HIDE-SELFIE',
            'pending_selfie',
        );

        $pendingFaceLog = $this->createPatrolLog(
            $guard,
            Carbon::parse('2026-09-03 20:10:00', config('app.timezone')),
            'CP-HIDE-FACE',
            'pending_face',
        );

        $response = $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index'));

        $response
            ->assertOk()
            ->assertSee('CP-HIDE-VALID')
            ->assertSee('/patrol-logs/'.$visibleLog->id.'/pdf', false)
            ->assertDontSee('/patrol-logs/'.$pendingSelfieLog->id.'/pdf', false)
            ->assertDontSee('/patrol-logs/'.$pendingFaceLog->id.'/pdf', false)
            ->assertDontSee('Pending Selfie');

        $this
            ->actingAs($guard->user)
            ->get(route('patrol-logs.index', ['status' => 'pending_selfie']))
            ->assertOk()
            ->assertSee('No patrol logs to display')
            ->assertDontSee('/patrol-logs/'.$pendingSelfieLog->id.'/pdf', false);
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

    private function createPatrolLog(Guard $guard, ?Carbon $scannedAt = null, ?string $checkpointCode = null, string $status = 'valid'): PatrolLog
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
            'rfid_status' => in_array($status, ['pending_face', 'pending_selfie'], true) ? 'valid' : $status,
            'facial_status' => in_array($status, ['pending_face', 'pending_selfie'], true) ? 'not_required' : 'verified',
            'status' => $status,
            'scanned_at' => $scannedAt ?? now(config('app.timezone')),
        ]);
    }
}

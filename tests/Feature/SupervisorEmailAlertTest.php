<?php

namespace Tests\Feature;

use App\Mail\IncidentReportSubmittedMail;
use App\Mail\RfidScanIssueMail;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\PatrolChecklist;
use App\Support\PatrolSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupervisorEmailAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_incident_submission_sends_supervisor_email_alert(): void
    {
        Storage::fake('public');
        Mail::fake();
        config(['mail.supervisor_alert_to' => 'supervisor@example.com']);

        [$user, $guard, $checkpoint] = $this->guardAndCheckpoint();

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'facial_status' => 'not_required',
            'status' => 'pending_selfie',
            'scanned_at' => now(PatrolSchedule::TIMEZONE),
        ]);

        $this
            ->actingAs($user)
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$this->normalChecklistStatuses(),
                'has_incident' => '1',
                'incident_category' => 'Facility Issue',
                'incident_priority' => 'high',
                'incident_description' => 'Broken light was observed near the checkpoint.',
                'incident_camera_images' => [
                    UploadedFile::fake()->image('incident-camera.jpg'),
                ],
            ])
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasNoErrors();

        Mail::assertSent(IncidentReportSubmittedMail::class, function (IncidentReportSubmittedMail $mail) {
            return $mail->hasTo('supervisor@example.com')
                && $mail->incidentReport->category === 'Facility Issue'
                && $mail->incidentReport->priority === 'high';
        });
    }

    public function test_invalid_rfid_scan_sends_supervisor_email_alert(): void
    {
        Mail::fake();
        config(['mail.supervisor_alert_to' => 'supervisor@example.com']);
        $this->travelToPatrolWindow();

        $this
            ->postJson(route('api.rfid-scan'), [
                'rfid_uid' => 'UNASSIGNED123',
                'device_uid' => 'ESP32-UNKNOWN',
            ])
            ->assertUnprocessable();

        Mail::assertSent(RfidScanIssueMail::class, function (RfidScanIssueMail $mail) {
            return $mail->hasTo('supervisor@example.com')
                && $mail->patrolLog->rfid_uid === 'UNASSIGNED123'
                && $mail->patrolLog->status === 'invalid';
        });
    }

    private function guardAndCheckpoint(): array
    {
        $this->travelToPatrolWindow();

        $user = User::factory()->create([
            'name' => 'Cherry Ann Himo',
            'username' => 'cherry.ann',
            'role' => 'guard',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-001',
            'name' => 'Cherry Ann Himo',
            'rfid_uid' => 'F33C8D37',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-IT-01',
            'name' => 'IT Building',
            'location' => 'IT Building',
            'device_uid' => 'ESP32-IT-01',
            'status' => 'active',
        ]);

        return [$user, $guard, $checkpoint];
    }

    private function areaSelfiePayload(): array
    {
        return [
            'area_selfie_capture' => $this->validImageDataUrl('area-selfie.jpg'),
            'area_selfie_captured_at' => now(PatrolSchedule::TIMEZONE)->toIso8601String(),
            'area_selfie_latitude' => '10.3456789',
            'area_selfie_longitude' => '124.1234567',
            'area_selfie_accuracy' => '8.25',
        ];
    }

    private function validImageDataUrl(string $name): string
    {
        $file = UploadedFile::fake()->image($name, 32, 32);

        return 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->getRealPath()));
    }

    private function normalChecklistStatuses(): array
    {
        return [
            'checklist_statuses' => array_fill_keys(array_keys(PatrolChecklist::items()), PatrolChecklist::STATUS_NORMAL),
        ];
    }

    private function travelToPatrolWindow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 17:00:00', PatrolSchedule::TIMEZONE));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\PatrolChecklist;
use App\Support\PatrolSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatrolAreaSelfieTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_rfid_scan_creates_pending_selfie_patrol_without_face_registration(): void
    {
        [$user, $guard, $checkpoint] = $this->guardAndCheckpoint();

        $response = $this->postJson(route('api.rfid-scan'), [
            'rfid_uid' => 'F33C8D37',
            'device_uid' => 'ESP32-IT-01',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'RFID scan accepted. Take the required area selfie.',
                'status' => 'pending_selfie',
            ]);

        $this->assertDatabaseHas('patrol_logs', [
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'not_required',
            'status' => 'pending_selfie',
        ]);
    }

    public function test_guard_can_complete_patrol_after_area_selfie_and_checklist(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog] = $this->pendingAreaSelfiePatrol();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$this->normalChecklistStatuses(),
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $patrolLog->refresh();

        $this->assertSame('not_required', $patrolLog->facial_status);
        $this->assertSame('valid', $patrolLog->status);
        $this->assertSame('image/jpeg', $patrolLog->area_selfie_mime_type);
        $this->assertNotNull($patrolLog->area_selfie_image_data);
        $this->assertEqualsWithDelta(10.3456789, (float) $patrolLog->area_selfie_latitude, 0.0000001);
        $this->assertEqualsWithDelta(124.1234567, (float) $patrolLog->area_selfie_longitude, 0.0000001);
        Storage::disk('public')->assertExists($patrolLog->area_selfie_path);

        $this->assertDatabaseHas('checklist_responses', [
            'patrol_log_id' => $patrolLog->id,
            'doors_locked' => 1,
            'lighting_ok' => 1,
            'cctv_alarm_checked' => 1,
            'no_unauthorized_person' => 1,
            'safety_hazard' => 1,
            'area_secure' => 1,
        ]);
        $this->assertDatabaseCount('checklist_proof_photos', 0);
    }

    public function test_guard_patrol_requires_area_selfie_before_submission(): void
    {
        [$user, $guard, $patrolLog] = $this->pendingAreaSelfiePatrol();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->normalChecklistStatuses(),
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors('area_selfie_capture');

        $this->assertSame($guard->id, $patrolLog->guard_id);
        $this->assertDatabaseCount('checklist_responses', 0);
    }

    public function test_issue_found_checklist_item_requires_its_own_proof_photo(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog] = $this->pendingAreaSelfiePatrol();
        $checklistStatuses = $this->normalChecklistStatuses();
        $checklistStatuses['checklist_statuses']['lighting_ok'] = PatrolChecklist::STATUS_ISSUE;

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$checklistStatuses,
                ...$this->checklistProofPhotos('area_secure'),
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors('checklist_photos');

        $this->assertSame($guard->id, $patrolLog->guard_id);
        $this->assertDatabaseCount('checklist_responses', 0);
        $this->assertDatabaseCount('checklist_proof_photos', 0);
    }

    public function test_patrol_scan_form_shows_area_selfie_step_instead_of_face_verification(): void
    {
        [$user] = $this->pendingAreaSelfiePatrol();

        $response = $this
            ->actingAs($user)
            ->get(route('patrol.scan'));

        $response
            ->assertOk()
            ->assertSee('Area Selfie')
            ->assertSee('Take Photo')
            ->assertSee('Take a photo at the checkpoint area')
            ->assertSee('Retake Photo')
            ->assertSee('area_selfie_capture', false)
            ->assertSee('areaSelfieCameraOpen', false)
            ->assertSee('openAreaSelfieCamera()', false)
            ->assertSee('captureAreaSelfie()', false)
            ->assertSee('checklist_statuses[doors_locked]', false)
            ->assertDontSee('Start Face Verification')
            ->assertDontSee('Face Verify')
            ->assertDontSee('face-verification-circle');
    }

    public function test_pending_scan_payload_reports_area_selfie_step(): void
    {
        [$user] = $this->pendingAreaSelfiePatrol();

        $response = $this
            ->actingAs($user)
            ->getJson(route('patrol.pending-scan'));

        $response
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('patrol_log.status', 'pending_selfie')
            ->assertJsonPath('patrol_log.facial_status', 'not_required')
            ->assertJsonPath('patrol_log.area_selfie_captured', false);
    }

    public function test_guard_can_view_saved_area_selfie_from_patrol_logs(): void
    {
        [$user, $guard, $patrolLog] = $this->pendingAreaSelfiePatrol();

        $patrolLog->update([
            'status' => 'valid',
            'area_selfie_mime_type' => 'image/jpeg',
            'area_selfie_image_data' => base64_encode('area-selfie-image'),
            'area_selfie_captured_at' => now(PatrolSchedule::TIMEZONE),
            'area_selfie_latitude' => 10.3456789,
            'area_selfie_longitude' => 124.1234567,
        ]);

        $this
            ->actingAs($user)
            ->get(route('patrol-logs.area-selfie.show', $patrolLog))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertContent('area-selfie-image');

        $this->assertSame($guard->id, $patrolLog->guard_id);
    }

    public function test_guard_can_submit_incident_with_area_selfie(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog] = $this->pendingAreaSelfiePatrol();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$this->normalChecklistStatuses(),
                'has_incident' => '1',
                'incident_category' => 'Theft / Missing Item',
                'incident_priority' => 'high',
                'incident_description' => 'Two people were seen near the restricted hallway.',
                'incident_images' => [
                    UploadedFile::fake()->image('hallway-1.jpg'),
                    UploadedFile::fake()->image('hallway-2.jpg'),
                ],
                'incident_camera_images' => [
                    UploadedFile::fake()->image('camera-photo.jpg'),
                ],
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $incident = IncidentReport::with('images')->first();

        $this->assertNotNull($incident);
        $this->assertSame($guard->id, $incident->guard_id);
        $this->assertSame('Theft / Missing Item', $incident->category);
        $this->assertCount(3, $incident->images);
        $this->assertSame(['upload', 'upload', 'camera'], $incident->images->pluck('source')->all());
        $this->assertSame($incident->images->first()->image_path, $incident->image_path);
        $incident->images->each(fn ($image) => Storage::disk('public')->assertExists($image->image_path));
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

    private function checklistProofPhotos(string $field): array
    {
        return [
            'checklist_photos' => [
                $field => UploadedFile::fake()->image("proof-{$field}.jpg", 24, 24),
            ],
        ];
    }

    private function normalChecklistStatuses(): array
    {
        return [
            'checklist_statuses' => array_fill_keys(array_keys(PatrolChecklist::items()), PatrolChecklist::STATUS_NORMAL),
        ];
    }

    private function pendingAreaSelfiePatrol(): array
    {
        [$user, $guard, $checkpoint] = $this->guardAndCheckpoint();

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'not_required',
            'status' => 'pending_selfie',
            'scanned_at' => now(PatrolSchedule::TIMEZONE),
        ]);

        return [$user, $guard, $patrolLog, $checkpoint];
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

    private function travelToPatrolWindow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 17:00:00', PatrolSchedule::TIMEZONE));
    }
}

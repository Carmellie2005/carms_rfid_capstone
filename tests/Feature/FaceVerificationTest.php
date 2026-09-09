<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\FaceVerification;
use App\Support\PatrolSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guard_patrol_is_valid_when_captured_face_descriptor_matches_registered_face(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
                'perimeter_checked' => '1',
                'equipment_functional' => '1',
                'cctv_alarm_checked' => '1',
                'fire_exits_clear' => '1',
                'emergency_equipment_accessible' => '1',
                'no_unauthorized_person' => '1',
                ...$this->checklistProofPhotos(),
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'verified',
            'status' => 'valid',
        ]);

        $this->assertDatabaseHas('face_verification_attempts', [
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'status' => 'verified',
            'liveness_challenge' => 'smile',
        ]);

        $this->assertNotNull($patrolLog->faceVerificationAttempts()->first()?->liveness_confirmed_at);

        $this->assertDatabaseHas('checklist_responses', [
            'patrol_log_id' => $patrolLog->id,
            'area_secure' => 1,
            'perimeter_checked' => 1,
            'equipment_functional' => 1,
            'cctv_alarm_checked' => 1,
            'fire_exits_clear' => 1,
            'emergency_equipment_accessible' => 1,
            'no_unauthorized_person' => 1,
        ]);

        $proofPhoto = $patrolLog->fresh()->checklistProofPhotos()->first();

        $this->assertNotNull($proofPhoto);
        $this->assertSame('area_secure', $proofPhoto->item_key);
        $this->assertSame('Area secure', $proofPhoto->item_label);
        $this->assertNotNull($proofPhoto->image_data);
        Storage::disk('public')->assertExists($proofPhoto->image_path);
    }

    public function test_guard_patrol_requires_checklist_proof_photo_when_face_is_verified(): void
    {
        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors('checklist_photos');

        $this->assertSame($guard->id, $patrolLog->guard_id);
        $this->assertDatabaseCount('checklist_responses', 0);
        $this->assertDatabaseCount('checklist_proof_photos', 0);
    }

    public function test_patrol_scan_form_shows_expanded_checklist_and_incident_categories(): void
    {
        [$user] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->get(route('patrol.scan'));

        $response
            ->assertOk()
            ->assertSee('Perimeter checked')
            ->assertSee('CCTV/alarm checked')
            ->assertSee('Emergency equipment accessible')
            ->assertSee('Theft or Robbery')
            ->assertSee('Medical Emergency')
            ->assertSee('Alarm or CCTV Issue')
            ->assertSee('Selected image previews')
            ->assertSee('Start Face Verification')
            ->assertSee('Light Assist')
            ->assertSee('checklist_photos[area_secure]', false)
            ->assertSee('checklistPhotoModalOpen', false)
            ->assertSee('openChecklistPhotoPreview', false)
            ->assertSee('Position your face inside the circle')
            ->assertSee('faceLivenessChallengeInstruction()', false)
            ->assertSee('Verifying live challenge...', false)
            ->assertSee('face-verification-circle')
            ->assertSee('face-auto-scan-ring')
            ->assertSee('camera-unmirrored')
            ->assertSee('beginAutomaticFaceVerification()', false)
            ->assertSee('NIGHT SHIFT')
            ->assertSee('Himo C.')
            ->assertSee('UID')
            ->assertDontSee('Open Camera')
            ->assertDontSee('Start Step 2')
            ->assertDontSee('Allow camera access, then keep your face inside the guide.')
            ->assertDontSee('Submit Incident')
            ->assertDontSee('Use this card at the checkpoint reader');
    }

    public function test_server_face_verification_endpoint_accepts_matching_face_without_exposing_saved_descriptor(): void
    {
        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->postJson(route('patrol.verify-face'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'verified' => true,
                'status' => 'verified',
            ]);

        $this->assertSame($guard->id, $patrolLog->guard_id);

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'facial_status' => 'pending',
            'status' => 'pending_face',
        ]);

        $this->assertDatabaseHas('face_verification_attempts', [
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'status' => 'verified',
            'liveness_challenge' => 'smile',
        ]);
    }

    public function test_server_face_verification_endpoint_rejects_descriptor_without_liveness_challenge(): void
    {
        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->postJson(route('patrol.verify-face'), [
                'patrol_log_id' => $patrolLog->id,
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['face_liveness_confirmed', 'face_liveness_challenge']);

        $this->assertSame($guard->id, $patrolLog->guard_id);
        $this->assertSame(0, $patrolLog->faceVerificationAttempts()->count());
    }

    public function test_server_face_verification_endpoint_rejects_expired_liveness_challenge(): void
    {
        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $this->withSession([
            'patrol_face_liveness_challenges' => [
                $patrolLog->id => 'turn-left',
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('patrol.verify-face'), [
                'patrol_log_id' => $patrolLog->id,
                'face_liveness_confirmed' => '1',
                'face_liveness_challenge' => 'smile',
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'verified' => false,
                'status' => 'failed',
                'message' => 'The face liveness challenge expired. Restart face verification after the RFID scan.',
            ]);

        $this->assertSame($guard->id, $patrolLog->guard_id);
        $this->assertSame(0, $patrolLog->faceVerificationAttempts()->count());
    }

    public function test_guard_can_refresh_after_face_verification_and_submit_checklist_without_reverification(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $this
            ->actingAs($user)
            ->postJson(route('patrol.verify-face'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
            ])
            ->assertOk()
            ->assertJson([
                'verified' => true,
                'status' => 'verified',
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('patrol.scan'));

        $response
            ->assertOk()
            ->assertSee('faceVerified: true', false)
            ->assertSee('openChecklist: true', false)
            ->assertSee('Face verified successfully. Complete the checklist.', false);

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'area_secure' => '1',
                'perimeter_checked' => '1',
                'equipment_functional' => '1',
                'cctv_alarm_checked' => '1',
                'fire_exits_clear' => '1',
                'emergency_equipment_accessible' => '1',
                'no_unauthorized_person' => '1',
                ...$this->checklistProofPhotos(),
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHas('status');

        $this->assertSame(1, $patrolLog->faceVerificationAttempts()->count());

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'verified',
            'status' => 'valid',
        ]);

        $this->assertDatabaseHas('checklist_responses', [
            'patrol_log_id' => $patrolLog->id,
            'area_secure' => 1,
            'perimeter_checked' => 1,
            'equipment_functional' => 1,
            'cctv_alarm_checked' => 1,
            'fire_exits_clear' => 1,
            'emergency_equipment_accessible' => 1,
            'no_unauthorized_person' => 1,
        ]);
        $this->assertDatabaseHas('checklist_proof_photos', [
            'patrol_log_id' => $patrolLog->id,
            'item_key' => 'area_secure',
        ]);
    }

    public function test_guard_patrol_rejects_exact_descriptor_replay(): void
    {
        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($descriptor),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'failed',
            'status' => 'suspicious',
        ]);

        $this->assertDatabaseHas('face_verification_attempts', [
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'status' => 'failed',
            'match_distance' => 0.0,
        ]);
    }

    public function test_guard_patrol_rejects_different_face_when_testing_bypass_is_disabled(): void
    {
        [$user, $guard, $patrolLog] = $this->pendingPatrolWithFaceDescriptor();
        $differentDescriptor = array_fill(0, 128, 1.0);

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($differentDescriptor),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'failed',
            'status' => 'suspicious',
        ]);

        $this->assertDatabaseHas('face_verification_attempts', [
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'status' => 'failed',
        ]);
    }

    public function test_guard_patrol_records_near_mismatch_as_suspicious_when_over_threshold(): void
    {
        [$user, $guard, $patrolLog] = $this->pendingPatrolWithFaceDescriptor();
        $nearWrongDescriptor = array_fill(0, 128, 0.19);

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($nearWrongDescriptor),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'failed',
            'status' => 'suspicious',
        ]);

        $attempt = $patrolLog->faceVerificationAttempts()->first();

        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertEqualsWithDelta(0.452548, (float) $attempt->match_distance, 0.000001);
        $this->assertEqualsWithDelta(0.42, (float) $attempt->match_threshold, 0.000001);
        $this->assertSame('Face did not match the guard pre-registered face reference after ESP32 RFID scan.', $attempt->notes);
    }

    public function test_guard_can_submit_incident_with_uploads_and_camera_photo(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
                ...$this->checklistProofPhotos(),
                'has_incident' => '1',
                'incident_category' => 'Theft or Robbery',
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
        $this->assertSame('Theft or Robbery', $incident->category);
        $this->assertCount(3, $incident->images);
        $this->assertSame(['upload', 'upload', 'camera'], $incident->images->pluck('source')->all());
        $this->assertSame($incident->images->first()->image_path, $incident->image_path);
        $this->assertNotNull($incident->images->first()->image_data);
        $this->assertStringStartsWith('image/', $incident->images->first()->mime_type);

        $incident->images->each(fn ($image) => Storage::disk('public')->assertExists($image->image_path));

        $this->assertDatabaseHas('checklist_proof_photos', [
            'patrol_log_id' => $patrolLog->id,
            'item_key' => 'area_secure',
        ]);
    }

    public function test_guard_incident_rejects_more_than_three_images(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
                ...$this->checklistProofPhotos(),
                'has_incident' => '1',
                'incident_category' => 'Suspicious Activity',
                'incident_priority' => 'high',
                'incident_description' => 'Too many evidence files were selected.',
                'incident_images' => [
                    UploadedFile::fake()->image('evidence-1.jpg'),
                    UploadedFile::fake()->image('evidence-2.jpg'),
                    UploadedFile::fake()->image('evidence-3.jpg'),
                ],
                'incident_camera_images' => [
                    UploadedFile::fake()->image('evidence-4.jpg'),
                ],
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors('incident_images');

        $this->assertDatabaseCount('incident_reports', 0);
        $this->assertDatabaseCount('incident_report_images', 0);
    }

    public function test_guard_incident_requires_image_evidence(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                'facial_status' => 'verified',
                ...$this->livenessPayload($patrolLog),
                'captured_descriptor' => json_encode($this->nearMatchingDescriptor($descriptor)),
                'face_capture' => $this->validFaceCapture(),
                'area_secure' => '1',
                ...$this->checklistProofPhotos(),
                'has_incident' => '1',
                'incident_category' => 'Suspicious Activity',
                'incident_priority' => 'high',
                'incident_description' => 'Incident evidence is required.',
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors('incident_images');

        $this->assertDatabaseCount('incident_reports', 0);
        $this->assertDatabaseCount('incident_report_images', 0);
    }

    public function test_pending_scan_ignores_older_pending_log_when_latest_scan_is_completed(): void
    {
        [$user, $guard, $oldPendingLog, $descriptor] = $this->pendingPatrolWithFaceDescriptor();

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $oldPendingLog->checkpoint_id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'verified',
            'status' => 'valid',
            'scanned_at' => $oldPendingLog->scanned_at->copy()->addSeconds(10),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('patrol.pending-scan'));

        $response
            ->assertOk()
            ->assertJson([
                'pending' => false,
                'patrol_log' => null,
            ]);
    }

    public function test_pending_scan_payload_includes_liveness_challenge(): void
    {
        [$user] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this
            ->actingAs($user)
            ->getJson(route('patrol.pending-scan'));

        $response
            ->assertOk()
            ->assertJsonPath('pending', true);

        $this->assertContains(
            $response->json('patrol_log.face_liveness_challenge'),
            FaceVerification::livenessChallenges(),
        );
        $this->assertNotContains('blink', FaceVerification::livenessChallenges());

        $this->assertNotEmpty($response->json('patrol_log.face_liveness_label'));
    }

    public function test_new_rfid_scan_expires_existing_pending_scan_for_same_guard(): void
    {
        [$user, $guard, $oldPendingLog] = $this->pendingPatrolWithFaceDescriptor();

        $response = $this->postJson(route('api.rfid-scan'), [
            'rfid_uid' => 'F33C8D37',
            'device_uid' => 'ESP32-IT-01',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'status' => 'pending_face',
            ]);

        $this->assertDatabaseHas('patrol_logs', [
            'id' => $oldPendingLog->id,
            'guard_id' => $guard->id,
            'facial_status' => 'expired',
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('patrol_logs', [
            'guard_id' => $guard->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'pending',
            'status' => 'pending_face',
        ]);

        $this->assertSame(
            '2026-08-26 05:00 PM',
            PatrolLog::latest('id')->first()?->scanned_at?->timezone(config('app.timezone'))->format('Y-m-d h:i A'),
        );
    }

    public function test_rfid_scan_accepts_get_query_from_hardware_reader(): void
    {
        [$user, $guard] = $this->pendingPatrolWithFaceDescriptor();
        PatrolLog::query()->delete();

        $response = $this->getJson(route('api.rfid-scan', [
            'uid' => 'F33C8D37',
            'device' => 'ESP32-IT-01',
        ]));

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'RFID scan accepted. Facial verification required.',
                'status' => 'pending_face',
            ]);

        $this->assertDatabaseHas('patrol_logs', [
            'guard_id' => $guard->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'pending',
            'status' => 'pending_face',
        ]);
    }

    public function test_rfid_scan_get_without_payload_shows_endpoint_help(): void
    {
        $response = $this->getJson(route('api.rfid-scan'));

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'RFID endpoint is ready. Send rfid_uid and device_uid as query parameters or JSON fields.',
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => false,
                'testing_notice' => null,
            ]);
    }

    public function test_rfid_scan_requires_face_registration_before_face_verification(): void
    {
        $this->travelToPatrolWindow();

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'no.face',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-NOFACE',
            'name' => 'No Face Guard',
            'rfid_uid' => 'RFID-NOFACE',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-NOFACE',
            'name' => 'No Face Checkpoint',
            'location' => 'Main Gate',
            'device_uid' => 'ESP32-NOFACE',
            'status' => 'active',
        ]);

        $response = $this->postJson(route('api.rfid-scan'), [
            'rfid_uid' => 'RFID-NOFACE',
            'device_uid' => 'ESP32-NOFACE',
        ]);

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Face registration is required before patrol verification.',
                'status' => 'profile_incomplete',
            ]);

        $this->assertDatabaseHas('patrol_logs', [
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-NOFACE',
            'rfid_status' => 'valid',
            'facial_status' => 'not_started',
            'status' => 'profile_incomplete',
        ]);
    }

    public function test_rfid_scan_is_allowed_anytime_when_schedule_is_not_enforced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00', PatrolSchedule::TIMEZONE));

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'day.scan',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-DAY',
            'name' => 'Day Scan Guard',
            'rfid_uid' => 'RFID-DAY',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-DAY',
            'name' => 'Day Checkpoint',
            'location' => 'Main Gate',
            'device_uid' => 'ESP32-DAY',
            'status' => 'active',
        ]);

        $guard->faceDescriptors()->create([
            'descriptor' => array_fill(0, 128, 0.15),
            'model_name' => 'face-api.js',
            'image_path' => 'guard-faces/day-scan.jpg',
            'is_primary' => true,
        ]);

        $response = $this->postJson(route('api.rfid-scan'), [
            'rfid_uid' => 'RFID-DAY',
            'device_uid' => 'ESP32-DAY',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'RFID scan accepted. Facial verification required.',
                'status' => 'pending_face',
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => false,
                'testing_notice' => null,
            ]);

        $this->assertDatabaseHas('patrol_logs', [
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-DAY',
            'checkpoint_code' => 'CP-DAY',
            'rfid_status' => 'valid',
            'facial_status' => 'pending',
            'status' => 'pending_face',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_scan_received',
            'subject_type' => PatrolLog::class,
        ]);
    }

    private function validFaceCapture(): string
    {
        $file = UploadedFile::fake()->image('face-capture.jpg', 20, 20);

        return 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->getRealPath()));
    }

    private function checklistProofPhotos(string $field = 'area_secure'): array
    {
        return [
            'checklist_photos' => [
                $field => UploadedFile::fake()->image("proof-{$field}.jpg", 24, 24),
            ],
        ];
    }

    private function livenessPayload(PatrolLog $patrolLog, string $challenge = 'smile'): array
    {
        $this->withSession([
            'patrol_face_liveness_challenges' => [
                $patrolLog->id => $challenge,
            ],
        ]);

        return [
            'face_liveness_confirmed' => '1',
            'face_liveness_challenge' => $challenge,
        ];
    }

    private function nearMatchingDescriptor(array $descriptor): array
    {
        return collect($descriptor)
            ->map(fn ($value) => round((float) $value + 0.01, 8))
            ->all();
    }

    private function pendingPatrolWithFaceDescriptor(): array
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

        $descriptor = array_fill(0, 128, 0.15);

        $guard->faceDescriptors()->create([
            'descriptor' => $descriptor,
            'model_name' => 'face-api.js',
            'image_path' => 'guard-faces/1/cherry-ann.jpg',
            'is_primary' => true,
        ]);

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'pending',
            'status' => 'pending_face',
            'scanned_at' => now(),
        ]);

        return [$user, $guard, $patrolLog, $descriptor];
    }

    private function travelToPatrolWindow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 17:00:00', PatrolSchedule::TIMEZONE));
    }
}

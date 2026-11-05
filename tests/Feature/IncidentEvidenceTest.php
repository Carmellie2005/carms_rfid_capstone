<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentEvidence;
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

class IncidentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guard_can_save_incident_evidence_with_capture_metadata(): void
    {
        Storage::fake('public');
        [$user, $guard] = $this->guardAndCheckpoint();

        $response = $this
            ->actingAs($user)
            ->postJson(route('patrol.incident-evidence.store'), [
                'image' => UploadedFile::fake()->image('far-incident.jpg', 320, 240),
                'captured_at' => '2026-11-05T10:00:00+08:00',
                'latitude' => '10.1234567',
                'longitude' => '124.1234567',
                'accuracy' => '8.50',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('evidence.original_name', 'far-incident.jpg')
            ->assertJsonPath('evidence.latitude', 10.1234567)
            ->assertJsonPath('evidence.longitude', 124.1234567)
            ->assertJsonPath('evidence.accuracy', 8.5);

        $evidence = IncidentEvidence::firstOrFail();

        $this->assertSame($guard->id, $evidence->guard_id);
        $this->assertNull($evidence->incident_report_id);
        $this->assertSame('2026-11-05 10:00:00', $evidence->captured_at->format('Y-m-d H:i:s'));
        Storage::disk('public')->assertExists($evidence->image_path);
    }

    public function test_saved_incident_evidence_can_be_attached_without_rescanning_rfid(): void
    {
        Storage::fake('public');
        [$user, $guard, $patrolLog] = $this->pendingPatrol();
        $image = UploadedFile::fake()->image('actual-location.jpg', 320, 240);
        $contents = file_get_contents($image->getRealPath());
        $path = 'incident-evidence/'.$guard->id.'/actual-location.jpg';

        Storage::disk('public')->put($path, $contents);

        $evidence = IncidentEvidence::create([
            'guard_id' => $guard->id,
            'image_path' => $path,
            'original_name' => 'actual-location.jpg',
            'mime_type' => 'image/jpeg',
            'image_data' => base64_encode($contents),
            'captured_at' => '2026-11-05 10:00:00',
            'latitude' => 10.1234567,
            'longitude' => 124.1234567,
            'accuracy' => 8.5,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$this->normalChecklistStatuses(),
                'has_incident' => '1',
                'incident_category' => 'Property Damage / Vandalism',
                'incident_priority' => 'high',
                'incident_description' => 'The incident was found away from the checkpoint.',
                'incident_camera_images' => [
                    UploadedFile::fake()->image('checkpoint-context.jpg', 320, 240),
                ],
                'incident_evidence_ids' => [$evidence->id],
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasNoErrors();

        $incident = IncidentReport::with('images.incidentEvidence')->firstOrFail();
        $reportImage = $incident->images->firstWhere('source', 'evidence_storage');

        $this->assertCount(2, $incident->images);
        $this->assertNotNull($reportImage);
        $this->assertSame('evidence_storage', $reportImage->source);
        $this->assertSame($evidence->id, $reportImage->incident_evidence_id);
        $this->assertSame($incident->id, $evidence->fresh()->incident_report_id);
        $this->assertSame(10.1234567, (float) $reportImage->incidentEvidence->latitude);
        Storage::disk('public')->assertExists($reportImage->image_path);
    }

    public function test_incident_submission_requires_two_images(): void
    {
        Storage::fake('public');
        [$user, $guard, $patrolLog] = $this->pendingPatrol();

        $response = $this
            ->actingAs($user)
            ->from(route('patrol.scan'))
            ->post(route('patrol.store'), [
                'patrol_log_id' => $patrolLog->id,
                ...$this->areaSelfiePayload(),
                ...$this->normalChecklistStatuses(),
                'has_incident' => '1',
                'incident_category' => 'Safety Hazard',
                'incident_priority' => 'normal',
                'incident_description' => 'Only one photo was attached.',
                'incident_camera_images' => [
                    UploadedFile::fake()->image('one-photo.jpg', 320, 240),
                ],
            ]);

        $response
            ->assertRedirect(route('patrol.scan'))
            ->assertSessionHasErrors(['incident_camera_images' => 'Please attach at least 2 incident photos before submitting the incident report.']);

        $this->assertDatabaseCount('incident_reports', 0);
        $this->assertSame($guard->id, $patrolLog->guard_id);
    }

    public function test_guard_can_open_their_evidence_library(): void
    {
        [$user] = $this->guardAndCheckpoint();

        $this
            ->actingAs($user)
            ->get(route('patrol.evidence'))
            ->assertOk()
            ->assertSee('My Evidence')
            ->assertSee('Incident Evidence')
            ->assertSee('Patrol Photos')
            ->assertSee('Use the X button to return to the system on your phone');
    }

    private function guardAndCheckpoint(): array
    {
        $user = User::factory()->create([
            'name' => 'Evidence Guard',
            'username' => 'evidence.guard',
            'role' => 'guard',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'BCP-EVIDENCE',
            'name' => 'Evidence Guard',
            'rfid_uid' => 'EVIDENCE-RFID',
            'shift' => '24-hour duty',
            'status' => 'active',
        ]);

        Checkpoint::create([
            'code' => 'CP-EVIDENCE-01',
            'name' => 'Evidence Checkpoint',
            'location' => 'Evidence Area',
            'device_uid' => 'ESP32-EVIDENCE-01',
            'status' => 'active',
        ]);

        return [$user, $guard];
    }

    private function pendingPatrol(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:30:00', PatrolSchedule::TIMEZONE));
        [$user, $guard] = $this->guardAndCheckpoint();
        $checkpoint = Checkpoint::firstOrFail();

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'EVIDENCE-RFID',
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'status' => 'pending_selfie',
            'scanned_at' => now(PatrolSchedule::TIMEZONE),
        ]);

        return [$user, $guard, $patrolLog];
    }

    private function areaSelfiePayload(): array
    {
        $file = UploadedFile::fake()->image('area-selfie.jpg', 32, 32);

        return [
            'area_selfie_capture' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->getRealPath())),
            'area_selfie_captured_at' => now(PatrolSchedule::TIMEZONE)->toIso8601String(),
            'area_selfie_latitude' => '10.3456789',
            'area_selfie_longitude' => '124.1234567',
            'area_selfie_accuracy' => '8.25',
        ];
    }

    private function normalChecklistStatuses(): array
    {
        return [
            'checklist_statuses' => array_fill_keys(array_keys(PatrolChecklist::items()), PatrolChecklist::STATUS_NORMAL),
        ];
    }
}

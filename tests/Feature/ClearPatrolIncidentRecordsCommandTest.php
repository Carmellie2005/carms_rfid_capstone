<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\ChecklistProofPhoto;
use App\Models\ChecklistResponse;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\IncidentReportImage;
use App\Models\NotificationRead;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClearPatrolIncidentRecordsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_clears_patrol_and_incident_records_without_deleting_accounts(): void
    {
        Storage::fake('public');

        [$user, $guard, $checkpoint, $patrolLog, $incidentReport] = $this->createPatrolAndIncidentRecords();

        $this->artisan('records:clear-patrol-incidents')
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('guards', ['id' => $guard->id]);
        $this->assertDatabaseHas('checkpoints', ['id' => $checkpoint->id]);
        $this->assertDatabaseHas('patrol_logs', ['id' => $patrolLog->id]);
        $this->assertDatabaseHas('incident_reports', ['id' => $incidentReport->id]);

        $this->artisan('records:clear-patrol-incidents --force')
            ->expectsOutputToContain('Patrol and incident records cleared.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('guards', ['id' => $guard->id]);
        $this->assertDatabaseHas('checkpoints', ['id' => $checkpoint->id]);

        $this->assertDatabaseMissing('patrol_logs', ['id' => $patrolLog->id]);
        $this->assertDatabaseMissing('checklist_responses', ['patrol_log_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('checklist_proof_photos', ['patrol_log_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('incident_reports', ['id' => $incidentReport->id]);
        $this->assertDatabaseMissing('incident_report_images', ['incident_report_id' => $incidentReport->id]);
        $this->assertDatabaseMissing('notification_reads', ['notifiable_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('notification_reads', ['notifiable_id' => $incidentReport->id]);
        $this->assertDatabaseMissing('audit_logs', ['subject_type' => PatrolLog::class, 'subject_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('audit_logs', ['subject_type' => IncidentReport::class, 'subject_id' => $incidentReport->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => Guard::class, 'subject_id' => $guard->id]);

        Storage::disk('public')->assertMissing('patrol-area-selfies/test.jpg');
        Storage::disk('public')->assertMissing('checklist-proof-photos/test.jpg');
        Storage::disk('public')->assertMissing('incident-reports/test-legacy.jpg');
        Storage::disk('public')->assertMissing('incident-reports/test-image.jpg');
    }

    private function createPatrolAndIncidentRecords(): array
    {
        $user = User::factory()->create(['role' => 'guard']);
        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'BCP-TEST',
            'name' => 'Test Guard',
            'rfid_uid' => 'TEST-RFID',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);
        $checkpoint = Checkpoint::create([
            'code' => 'CP-TEST',
            'name' => 'Test Checkpoint',
            'location' => 'Test Area',
            'device_uid' => 'ESP32-TEST',
            'status' => 'active',
        ]);
        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'TEST-RFID',
            'checkpoint_code' => 'CP-TEST',
            'rfid_status' => 'valid',
            'status' => 'valid',
            'area_selfie_path' => 'patrol-area-selfies/test.jpg',
            'area_selfie_mime_type' => 'image/jpeg',
            'area_selfie_image_data' => base64_encode('area-selfie'),
            'area_selfie_captured_at' => now(),
            'area_selfie_latitude' => 10.1,
            'area_selfie_longitude' => 124.1,
            'scanned_at' => now(),
        ]);
        $checklist = ChecklistResponse::create([
            'patrol_log_id' => $patrolLog->id,
            'doors_checked' => true,
        ]);
        ChecklistProofPhoto::create([
            'checklist_response_id' => $checklist->id,
            'patrol_log_id' => $patrolLog->id,
            'item_key' => 'doors_checked',
            'item_label' => 'Entry points secured',
            'image_path' => 'checklist-proof-photos/test.jpg',
            'mime_type' => 'image/jpeg',
            'image_data' => base64_encode('checklist-proof'),
            'sort_order' => 1,
        ]);
        $incidentReport = IncidentReport::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'category' => 'Facility Issue',
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => now(),
            'description' => 'Test incident.',
            'image_path' => 'incident-reports/test-legacy.jpg',
            'status' => 'submitted',
        ]);
        IncidentReportImage::create([
            'incident_report_id' => $incidentReport->id,
            'image_path' => 'incident-reports/test-image.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'image_data' => base64_encode('incident-image'),
            'source' => 'camera',
            'sort_order' => 1,
        ]);
        NotificationRead::create([
            'user_id' => $user->id,
            'notifiable_type' => PatrolLog::class,
            'notifiable_id' => $patrolLog->id,
            'read_at' => now(),
        ]);
        NotificationRead::create([
            'user_id' => User::factory()->create(['role' => 'admin'])->id,
            'notifiable_type' => IncidentReport::class,
            'notifiable_id' => $incidentReport->id,
            'read_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'actor_name' => $guard->name,
            'action' => 'patrol_completed',
            'description' => 'Patrol completed.',
            'subject_type' => PatrolLog::class,
            'subject_id' => $patrolLog->id,
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'actor_name' => $guard->name,
            'action' => 'incident_submitted',
            'description' => 'Incident submitted.',
            'subject_type' => IncidentReport::class,
            'subject_id' => $incidentReport->id,
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'actor_name' => $guard->name,
            'action' => 'guard_updated',
            'description' => 'Guard account updated.',
            'subject_type' => Guard::class,
            'subject_id' => $guard->id,
        ]);

        foreach ([
            'patrol-area-selfies/test.jpg',
            'checklist-proof-photos/test.jpg',
            'incident-reports/test-legacy.jpg',
            'incident-reports/test-image.jpg',
        ] as $path) {
            Storage::disk('public')->put($path, 'image');
        }

        return [$user, $guard, $checkpoint, $patrolLog, $incidentReport];
    }
}

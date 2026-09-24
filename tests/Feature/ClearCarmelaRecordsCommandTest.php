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

class ClearCarmelaRecordsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_clears_carmelas_records_but_keeps_her_profile_information(): void
    {
        Storage::fake('public');

        [$user, $guard, $patrolLog, $incidentReport] = $this->createCarmelaRecords();
        $otherGuard = $this->createOtherGuardWithLog();

        $this->artisan('guard:clear-carmela-records')
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('guards', ['id' => $guard->id]);
        $this->assertDatabaseHas('patrol_logs', ['id' => $patrolLog->id]);
        $this->assertDatabaseHas('incident_reports', ['id' => $incidentReport->id]);
        $this->assertDatabaseCount('audit_logs', 4);

        $this->artisan('guard:clear-carmela-records --force')
            ->expectsOutputToContain('Carmela activity records cleared.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('guards', ['id' => $guard->id]);

        $this->assertDatabaseMissing('patrol_logs', ['id' => $patrolLog->id]);
        $this->assertDatabaseMissing('checklist_responses', ['patrol_log_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('checklist_proof_photos', ['patrol_log_id' => $patrolLog->id]);
        $this->assertDatabaseMissing('incident_reports', ['id' => $incidentReport->id]);
        $this->assertDatabaseMissing('incident_report_images', ['incident_report_id' => $incidentReport->id]);
        $this->assertDatabaseMissing('notification_reads', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('audit_logs', ['actor_name' => 'Carmela Bihay Hernandez']);

        Storage::disk('public')->assertMissing('patrol-area-selfies/carmela.jpg');
        Storage::disk('public')->assertMissing('checklist-proof-photos/carmela.jpg');
        Storage::disk('public')->assertMissing('incident-reports/carmela.jpg');

        $this->assertDatabaseHas('guards', ['id' => $otherGuard->id]);
        $this->assertDatabaseHas('patrol_logs', ['guard_id' => $otherGuard->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_name' => $otherGuard->name]);
    }

    private function createCarmelaRecords(): array
    {
        $user = User::factory()->create([
            'name' => 'Carmela Bihay Hernandez',
            'email' => 'carmela.bihay.hernandez@guard.local',
            'username' => 'carmela.bihay.hernandez',
            'role' => 'guard',
        ]);
        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'TEST-01',
            'name' => 'Carmela Bihay Hernandez',
            'email' => 'carmela.bihay.hernandez@guard.local',
            'rfid_uid' => 'F33C8D37',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);
        $checkpoint = Checkpoint::create([
            'code' => 'CP-IT-01',
            'name' => 'IT',
            'location' => 'IT',
            'device_uid' => 'ESP32-IT-01',
            'status' => 'active',
        ]);
        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'F33C8D37',
            'checkpoint_code' => 'CP-IT-01',
            'rfid_status' => 'valid',
            'facial_status' => 'not_required',
            'status' => 'valid',
            'area_selfie_path' => 'patrol-area-selfies/carmela.jpg',
            'area_selfie_mime_type' => 'image/jpeg',
            'area_selfie_image_data' => base64_encode('area-selfie'),
            'area_selfie_captured_at' => now(),
            'area_selfie_latitude' => 10.1,
            'area_selfie_longitude' => 124.1,
            'scanned_at' => now(),
        ]);
        $checklist = ChecklistResponse::create([
            'patrol_log_id' => $patrolLog->id,
            'area_secure' => true,
        ]);
        ChecklistProofPhoto::create([
            'checklist_response_id' => $checklist->id,
            'patrol_log_id' => $patrolLog->id,
            'item_key' => 'area_secure',
            'item_label' => 'Area condition recorded with photo proof',
            'image_path' => 'checklist-proof-photos/carmela.jpg',
            'mime_type' => 'image/jpeg',
            'image_data' => base64_encode('checklist-proof'),
            'sort_order' => 1,
        ]);
        $incidentReport = IncidentReport::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'category' => 'Suspicious Activity',
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => now(),
            'description' => 'Test incident.',
            'status' => 'submitted',
        ]);
        IncidentReportImage::create([
            'incident_report_id' => $incidentReport->id,
            'image_path' => 'incident-reports/carmela.jpg',
            'original_name' => 'carmela.jpg',
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
            'actor_name' => 'Carmela Bihay Hernandez',
            'action' => 'user_login',
            'description' => 'User logged in.',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
        AuditLog::create([
            'user_id' => User::factory()->create(['role' => 'admin'])->id,
            'actor_name' => 'Supervisor',
            'action' => 'patrol_completed',
            'description' => 'Carmela patrol completed.',
            'subject_type' => PatrolLog::class,
            'subject_id' => $patrolLog->id,
        ]);
        AuditLog::create([
            'user_id' => User::factory()->create(['role' => 'admin'])->id,
            'actor_name' => 'Supervisor',
            'action' => 'incident_submitted',
            'description' => 'Carmela incident submitted.',
            'subject_type' => IncidentReport::class,
            'subject_id' => $incidentReport->id,
        ]);

        foreach ([
            'patrol-area-selfies/carmela.jpg',
            'checklist-proof-photos/carmela.jpg',
            'incident-reports/carmela.jpg',
        ] as $path) {
            Storage::disk('public')->put($path, 'image');
        }

        return [$user, $guard, $patrolLog, $incidentReport];
    }

    private function createOtherGuardWithLog(): Guard
    {
        $user = User::factory()->create(['role' => 'guard']);
        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-OTHER',
            'name' => 'Other Guard',
            'email' => 'other.guard@guard.local',
            'rfid_uid' => 'OTHER-RFID',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);
        $checkpoint = Checkpoint::create([
            'code' => 'CP-OTHER',
            'name' => 'Other',
            'location' => 'Other',
            'device_uid' => 'ESP32-OTHER',
            'status' => 'active',
        ]);

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'OTHER-RFID',
            'checkpoint_code' => 'CP-OTHER',
            'rfid_status' => 'valid',
            'facial_status' => 'not_required',
            'status' => 'valid',
            'scanned_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'actor_name' => $guard->name,
            'action' => 'user_login',
            'description' => 'Other guard logged in.',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);

        return $guard;
    }
}

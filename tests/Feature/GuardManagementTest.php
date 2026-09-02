<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\FaceVerificationAttempt;
use App\Models\Guard;
use App\Models\GuardFaceDescriptor;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_create_guard_without_face_upload(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('guards.store'), [
                'employee_no' => 'SG-NO-UPLOAD',
                'name' => 'No Upload Guard',
                'email' => 'no.upload.guard@example.com',
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-NO-UPLOAD',
                'face_reference' => null,
                'shift' => 'Night Shift',
                'status' => 'active',
                'notes' => null,
                'username' => 'no.upload.guard',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $guard = Guard::where('employee_no', 'SG-NO-UPLOAD')->firstOrFail();

        $this->assertSame('RFID-NO-UPLOAD', $guard->rfid_uid);
        $this->assertSame(0, $guard->faceDescriptors()->count());
    }

    public function test_supervisor_guard_form_has_no_face_upload_field(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('guards.create'));

        $response
            ->assertOk()
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('aria-label="Show confirm password"', false)
            ->assertDontSee('name="face_images[]', false)
            ->assertDontSee('Face Image');
    }

    public function test_guard_index_uses_modal_for_new_guard(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('guards.index'));

        $response
            ->assertOk()
            ->assertSee('x-on:click="$dispatch(\'open-create-guard\')"', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('aria-label="Show confirm password"', false)
            ->assertSee('Create Guard Account')
            ->assertSee('Guard Management')
            ->assertSee('Registered guards, RFID cards, and live face registration status')
            ->assertSee('Guard Profiles')
            ->assertDontSee('Register Guard');
    }

    public function test_guard_index_hides_unregistered_rfid_placeholder(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        Guard::create([
            'employee_no' => 'UNKNOWN',
            'name' => 'Unregistered RFID Card',
            'rfid_uid' => 'UNKNOWN',
            'status' => 'inactive',
        ]);

        Guard::create([
            'user_id' => User::factory()->create(['role' => 'guard'])->id,
            'employee_no' => 'SG-VISIBLE',
            'name' => 'Visible Guard',
            'email' => 'visible.guard@example.com',
            'rfid_uid' => 'RFID-VISIBLE',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('guards.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Guard')
            ->assertDontSee('Unregistered RFID Card');
    }

    public function test_supervisor_can_fetch_guard_records_for_modal(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'modal.guard',
        ]);

        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-MODAL',
            'name' => 'Modal Guard',
            'email' => 'modal.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => 'RFID-MODAL',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        GuardFaceDescriptor::create([
            'guard_id' => $guard->id,
            'descriptor' => array_fill(0, 128, 0.12),
            'model_name' => 'face-api.js',
            'is_primary' => true,
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-MODAL',
            'name' => 'Modal Checkpoint',
            'location' => 'Main Gate',
            'device_uid' => 'ESP32-MODAL',
            'status' => 'active',
        ]);

        $patrolLog = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => $guard->rfid_uid,
            'checkpoint_code' => $checkpoint->code,
            'rfid_status' => 'valid',
            'facial_status' => 'verified',
            'status' => 'completed',
            'scanned_at' => now(),
        ]);

        IncidentReport::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => 'Broken Light',
            'incident_type' => 'facility',
            'category' => 'facility',
            'priority' => 'high',
            'severity' => 'medium',
            'location' => 'Main Gate',
            'incident_at' => now(),
            'reported_at' => now(),
            'description' => 'A light was not working.',
            'status' => 'submitted',
        ]);

        FaceVerificationAttempt::create([
            'patrol_log_id' => $patrolLog->id,
            'guard_id' => $guard->id,
            'status' => 'failed',
            'match_distance' => 0.720000,
            'match_threshold' => 0.420000,
            'model_name' => 'face-api.js',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->getJson(route('guards.records', $guard));

        $response
            ->assertOk()
            ->assertJsonPath('guard.name', 'Modal Guard')
            ->assertJsonPath('guard.face_registration', 'Registered')
            ->assertJsonPath('stats.total_scans', 1)
            ->assertJsonPath('stats.incident_reports', 1)
            ->assertJsonPath('stats.failed_face_attempts', 1)
            ->assertJsonPath('patrol_logs.0.checkpoint', 'Modal Checkpoint')
            ->assertJsonPath('incidents.0.title', 'Broken Light')
            ->assertJsonPath('face_attempts.0.status', 'failed');
    }

    public function test_guard_cannot_fetch_guard_records_for_modal(): void
    {
        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'regular.guard',
        ]);

        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-REGULAR',
            'name' => 'Regular Guard',
            'email' => 'regular.guard@example.com',
            'phone' => '09170000000',
            'rfid_uid' => 'RFID-REGULAR',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $this
            ->actingAs($guardUser)
            ->getJson(route('guards.records', $guard))
            ->assertForbidden();
    }
}

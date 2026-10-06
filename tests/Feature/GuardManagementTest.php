<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_create_guard_with_core_details(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('guards.store'), [
                'employee_no' => 'BCP-001',
                'name' => 'No Upload Guard',
                'email' => 'no.upload.guard@example.com',
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-NO-UPLOAD',
                'shift' => 'Night Shift',
                'status' => 'active',
                'notes' => null,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $guard = Guard::where('employee_no', 'BCP-001')->firstOrFail();

        $this->assertSame('RFID-NO-UPLOAD', $guard->rfid_uid);
        $user = $guard->user()->firstOrFail();
        $this->assertSame('bcp-001', $user->username);
        $this->assertTrue($user->must_change_password);
    }

    public function test_guard_shift_is_locked_to_night_shift(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $this
            ->actingAs($supervisor)
            ->post(route('guards.store'), [
                'employee_no' => 'BCP-002',
                'name' => 'Locked Shift Guard',
                'email' => 'locked.shift@example.com',
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-LOCKED-SHIFT',
                'shift' => 'Day Shift',
                'status' => 'active',
                'notes' => null,
                'username' => 'locked.shift',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $guard = Guard::where('employee_no', 'BCP-002')->firstOrFail();

        $this->assertSame('Night Shift', $guard->shift);

        $this
            ->actingAs($supervisor)
            ->put(route('guards.update', $guard), [
                'employee_no' => 'BCP-002',
                'name' => 'Locked Shift Guard',
                'email' => 'locked.shift@example.com',
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-LOCKED-SHIFT',
                'shift' => 'Day Shift',
                'status' => 'active',
                'notes' => null,
                'username' => 'locked.shift',
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $this->assertSame('Night Shift', $guard->refresh()->shift);
    }

    public function test_supervisor_cannot_assign_rfid_card_already_used_by_another_guard(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        Guard::create([
            'user_id' => User::factory()->create(['role' => 'guard'])->id,
            'employee_no' => 'SG-EXISTING',
            'name' => 'Existing Guard',
            'email' => 'existing.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => '8049C4CC',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $this
            ->actingAs($supervisor)
            ->post(route('guards.store'), [
                'employee_no' => 'BCP-003',
                'name' => 'Duplicate Guard',
                'email' => 'duplicate.guard@example.com',
                'phone' => '09170000000',
                'rfid_uid' => '8049c4cc',
                'shift' => 'Night Shift',
                'status' => 'active',
                'notes' => null,
                'username' => 'duplicate.guard',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors([
                'rfid_uid' => 'This RFID card is already assigned to another guard. Please use another card or update the existing guard profile.',
            ]);
    }

    public function test_supervisor_password_reset_requires_guard_to_change_password(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);
        $guardUser = User::factory()->create([
            'role' => 'guard',
            'username' => 'reset.guard',
            'email' => 'reset.guard@example.com',
            'must_change_password' => false,
        ]);
        $guard = Guard::create([
            'user_id' => $guardUser->id,
            'employee_no' => 'SG-RESET',
            'name' => 'Reset Guard',
            'email' => 'reset.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => 'RFID-RESET',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->put(route('guards.update', $guard), [
                'employee_no' => 'SG-RESET',
                'name' => 'Reset Guard',
                'email' => 'reset.guard@example.com',
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-RESET',
                'shift' => 'Night Shift',
                'status' => 'active',
                'notes' => null,
                'username' => 'reset.guard',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $this->assertTrue($guardUser->refresh()->must_change_password);
    }

    public function test_supervisor_can_create_guard_with_guard_number_as_username(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('guards.store'), [
                'employee_no' => 'BCP-004',
                'name' => 'Guard Number Login Guard',
                'email' => null,
                'phone' => '09171234567',
                'rfid_uid' => 'RFID-EMAIL-LOGIN',
                'shift' => 'Night Shift',
                'status' => 'active',
                'notes' => null,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guards.index'));

        $guard = Guard::where('employee_no', 'BCP-004')->firstOrFail();
        $user = User::where('username', 'bcp-004')->firstOrFail();

        $this->assertSame($user->id, $guard->user_id);
        $this->assertSame('bcp-004@guards.campusrfid.local', $user->email);
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
            ->assertSee('rfidEnrollmentLatestUrl', false)
            ->assertSee('Scan Card')
            ->assertSee("startRfidEnrollment('create_rfid_uid')", false)
            ->assertSee('Create Guard Account')
            ->assertSee('The guard will sign in using the Guard No. above, for example BCP-001.')
            ->assertSee('Guard Management')
            ->assertSee('Registered guards, RFID cards, shifts, and login accounts')
            ->assertSee('Guard Profiles')
            ->assertSee('loadGuardRecordPatrolPage', false)
            ->assertSee('recordPatrolShowingLabel', false)
            ->assertDontSee('<th class="px-5 py-3">Account</th>', false)
            ->assertDontSee('<dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Account</dt>', false)
            ->assertDontSee('selectedGuard?.username', false)
            ->assertDontSee('selectedGuard.role.charAt', false)
            ->assertDontSee('selectedGuard?.email', false)
            ->assertDontSee('create_username', false)
            ->assertDontSee('selectedGuard?.shift', false)
            ->assertDontSee('Shift / Status')
            ->assertSee('dark:bg-slate-900', false)
            ->assertSee('dark:bg-slate-950/45', false)
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

        $response = $this
            ->actingAs($supervisor)
            ->getJson(route('guards.records', $guard));

        $response
            ->assertOk()
            ->assertJsonPath('guard.name', 'Modal Guard')
            ->assertJsonPath('stats.total_scans', 1)
            ->assertJsonPath('stats.incident_reports', 1)
            ->assertJsonPath('patrol_logs.0.checkpoint', 'Modal Checkpoint')
            ->assertJsonPath('incidents.0.title', 'Broken Light');
    }

    public function test_guard_records_patrol_scans_are_paginated_for_modal(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $guard = Guard::create([
            'user_id' => User::factory()->create(['role' => 'guard'])->id,
            'employee_no' => 'SG-PAGED-MODAL',
            'name' => 'Paged Modal Guard',
            'email' => 'paged.modal.guard@example.com',
            'phone' => '09171234567',
            'rfid_uid' => 'RFID-PAGED-MODAL',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        foreach (range(1, 7) as $number) {
            PatrolLog::create([
                'guard_id' => $guard->id,
                'checkpoint_id' => null,
                'rfid_uid' => $guard->rfid_uid,
                'checkpoint_code' => sprintf('CP-PAGED-%02d', $number),
                'rfid_status' => 'valid',
                'status' => 'valid',
                'scanned_at' => now()->addMinutes($number),
            ]);
        }

        $this
            ->actingAs($supervisor)
            ->getJson(route('guards.records', $guard))
            ->assertOk()
            ->assertJsonCount(6, 'patrol_logs')
            ->assertJsonPath('patrol_logs.0.checkpoint_code', 'CP-PAGED-07')
            ->assertJsonPath('patrol_pagination.total', 7)
            ->assertJsonPath('patrol_pagination.current_page', 1)
            ->assertJsonPath('patrol_pagination.has_more_pages', true);

        $this
            ->actingAs($supervisor)
            ->getJson(route('guards.records', ['guard' => $guard, 'patrol_page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'patrol_logs')
            ->assertJsonPath('patrol_logs.0.checkpoint_code', 'CP-PAGED-01')
            ->assertJsonPath('patrol_pagination.current_page', 2)
            ->assertJsonPath('patrol_pagination.on_first_page', false);
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

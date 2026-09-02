<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_dropdown_shows_recent_unread_alerts_and_view_all_link(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 6) as $index) {
            IncidentReport::create([
                'category' => 'Recent Alert '.$index,
                'priority' => 'high',
                'severity' => 'high',
                'incident_at' => now()->subMinutes($index),
                'description' => 'Notification dropdown test.',
                'status' => 'submitted',
            ]);
        }

        $this
            ->actingAs($supervisor)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Latest 5 of 6 unread alerts')
            ->assertSee('View all notifications')
            ->assertSee('open-notifications-modal', false)
            ->assertSee('embedded=1', false)
            ->assertSee('Recent Alert 1')
            ->assertSee('Recent Alert 5')
            ->assertDontSee('Recent Alert 6');
    }

    public function test_user_can_view_all_notifications_with_pagination(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 13) as $index) {
            IncidentReport::create([
                'category' => sprintf('Paged Alert %02d', $index),
                'priority' => 'normal',
                'severity' => 'medium',
                'incident_at' => now()->subMinutes($index),
                'description' => 'Notification page test.',
                'status' => 'submitted',
            ]);
        }

        $this
            ->actingAs($supervisor)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('All Notifications')
            ->assertSee('13 unread alerts')
            ->assertSee('Paged Alert 01')
            ->assertSee('Paged Alert 12')
            ->assertDontSee('Paged Alert 13');

        $this
            ->actingAs($supervisor)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Paged Alert 13')
            ->assertDontSee('Paged Alert 12');
    }

    public function test_user_can_mark_notification_as_read_and_activity_is_logged(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $incident = IncidentReport::create([
            'category' => 'Suspicious Activity',
            'priority' => 'high',
            'severity' => 'high',
            'incident_at' => now(),
            'description' => 'Unauthorized person seen near the gate.',
            'status' => 'submitted',
        ]);

        $this
            ->actingAs($supervisor)
            ->get('/profile')
            ->assertOk()
            ->assertSee('1 unread alert')
            ->assertSee('Suspicious Activity')
            ->assertSee('Mark as read');

        $response = $this
            ->actingAs($supervisor)
            ->from('/profile')
            ->post(route('notifications.read'), [
                'type' => 'incident',
                'id' => $incident->id,
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'Notification marked as read.');

        $this->assertDatabaseHas('notification_reads', [
            'user_id' => $supervisor->id,
            'notifiable_type' => IncidentReport::class,
            'notifiable_id' => $incident->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'action' => 'notification_marked_read',
            'subject_type' => IncidentReport::class,
            'subject_id' => $incident->id,
        ]);

        $this
            ->actingAs($supervisor)
            ->get('/profile')
            ->assertOk()
            ->assertSee('0 unread alerts')
            ->assertSee('No alerts right now')
            ->assertDontSee('Suspicious Activity');
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = Guard::create([
            'employee_no' => 'SG-NOTIFY',
            'name' => 'Notification Guard',
            'rfid_uid' => 'RFID-NOTIFY',
            'status' => 'active',
        ]);
        $checkpoint = Checkpoint::create([
            'code' => 'CP-NOTIFY',
            'name' => 'Notification Checkpoint',
            'location' => 'Main Gate',
            'status' => 'active',
        ]);
        $incident = IncidentReport::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'category' => 'Emergency',
            'priority' => 'critical',
            'severity' => 'critical',
            'incident_at' => now(),
            'description' => 'Emergency reported during patrol.',
            'status' => 'submitted',
        ]);
        $patrol = PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-NOTIFY',
            'checkpoint_code' => 'CP-NOTIFY',
            'rfid_status' => 'valid',
            'facial_status' => 'failed',
            'status' => 'suspicious',
            'scanned_at' => now('Asia/Manila'),
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->from('/profile')
            ->post(route('notifications.read-all'));

        $response
            ->assertRedirect('/profile')
            ->assertSessionHas('status', '2 notifications marked as read.');

        $this->assertDatabaseHas('notification_reads', [
            'user_id' => $supervisor->id,
            'notifiable_type' => IncidentReport::class,
            'notifiable_id' => $incident->id,
        ]);

        $this->assertDatabaseHas('notification_reads', [
            'user_id' => $supervisor->id,
            'notifiable_type' => PatrolLog::class,
            'notifiable_id' => $patrol->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'action' => 'notifications_marked_read',
        ]);
    }

    public function test_guard_cannot_mark_another_guards_notification_as_read(): void
    {
        $firstUser = User::factory()->create(['role' => 'guard']);
        $secondUser = User::factory()->create(['role' => 'guard']);
        $firstGuard = Guard::create([
            'user_id' => $firstUser->id,
            'employee_no' => 'SG-FIRST',
            'name' => 'First Guard',
            'rfid_uid' => 'RFID-FIRST',
            'status' => 'active',
        ]);
        Guard::create([
            'user_id' => $secondUser->id,
            'employee_no' => 'SG-SECOND',
            'name' => 'Second Guard',
            'rfid_uid' => 'RFID-SECOND',
            'status' => 'active',
        ]);
        $incident = IncidentReport::create([
            'guard_id' => $firstGuard->id,
            'category' => 'Safety Hazard',
            'priority' => 'normal',
            'severity' => 'medium',
            'incident_at' => now(),
            'description' => 'Hazard reported by another guard.',
            'status' => 'submitted',
        ]);

        $this
            ->actingAs($secondUser)
            ->post(route('notifications.read'), [
                'type' => 'incident',
                'id' => $incident->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('notification_reads', [
            'user_id' => $secondUser->id,
            'notifiable_type' => IncidentReport::class,
            'notifiable_id' => $incident->id,
        ]);
    }
}

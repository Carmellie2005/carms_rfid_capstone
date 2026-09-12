<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Services\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_store_and_remove_push_subscription(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/supervisor-device';

        $this
            ->actingAs($supervisor)
            ->postJson(route('push.subscriptions.store'), [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'public-browser-key',
                    'auth' => 'auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $supervisor->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'content_encoding' => 'aes128gcm',
        ]);

        $this
            ->actingAs($supervisor)
            ->deleteJson(route('push.subscriptions.destroy'), [
                'endpoint' => $endpoint,
            ])
            ->assertOk()
            ->assertJson(['status' => 'unsubscribed']);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $supervisor->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function test_push_subscription_routes_are_supervisor_only(): void
    {
        $guard = User::factory()->create(['role' => 'guard']);

        $this
            ->actingAs($guard)
            ->getJson(route('push.config'))
            ->assertForbidden();

        $this
            ->actingAs($guard)
            ->postJson(route('push.subscriptions.store'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/guard-device',
                'keys' => [
                    'p256dh' => 'public-browser-key',
                    'auth' => 'auth-token',
                ],
            ])
            ->assertForbidden();
    }

    public function test_push_config_exposes_only_public_vapid_key(): void
    {
        config([
            'services.webpush.vapid.subject' => 'https://example.test',
            'services.webpush.vapid.public_key' => 'public-vapid-key',
            'services.webpush.vapid.private_key' => 'private-vapid-key',
        ]);

        $supervisor = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($supervisor)
            ->getJson(route('push.config'))
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'publicKey' => 'public-vapid-key',
            ])
            ->assertJsonMissing([
                'privateKey' => 'private-vapid-key',
            ]);
    }

    public function test_review_records_trigger_supervisor_web_push_sender(): void
    {
        $this->mock(WebPushNotifier::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('sendSupervisorIncidentAlert')
                ->once()
                ->with(Mockery::type(IncidentReport::class));

            $mock
                ->shouldReceive('sendSupervisorPatrolAlert')
                ->once()
                ->with(Mockery::type(PatrolLog::class));
        });

        $guard = Guard::create([
            'employee_no' => 'SG-PUSH',
            'name' => 'Push Guard',
            'rfid_uid' => 'RFID-PUSH',
            'status' => 'active',
        ]);
        $checkpoint = Checkpoint::create([
            'code' => 'CP-PUSH',
            'name' => 'Push Checkpoint',
            'location' => 'Main Gate',
            'status' => 'active',
        ]);

        IncidentReport::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'category' => 'Push Incident',
            'priority' => 'high',
            'severity' => 'high',
            'incident_at' => now(),
            'description' => 'Incident for push notification.',
            'status' => 'submitted',
        ]);

        PatrolLog::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'rfid_uid' => 'RFID-PUSH',
            'checkpoint_code' => 'CP-PUSH',
            'rfid_status' => 'invalid',
            'facial_status' => 'not_started',
            'status' => 'invalid',
            'scanned_at' => now('Asia/Manila'),
        ]);
    }
}

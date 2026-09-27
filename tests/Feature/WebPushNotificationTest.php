<?php

namespace Tests\Feature;

use App\Models\PatrolLog;
use App\Models\User;
use App\Services\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_save_and_remove_web_push_subscription(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);
        $endpoint = 'https://example.com/push/subscription-1';

        $this
            ->actingAs($supervisor)
            ->postJson(route('push-notifications.subscribe'), $this->subscriptionPayload($endpoint))
            ->assertOk()
            ->assertJsonPath('message', 'Web push alerts enabled for this device.');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $supervisor->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'content_encoding' => 'aes128gcm',
        ]);

        $this
            ->actingAs($supervisor)
            ->deleteJson(route('push-notifications.unsubscribe'), [
                'endpoint' => $endpoint,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Web push alerts disabled for this device.');

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function test_only_supervisors_can_manage_web_push_subscriptions(): void
    {
        $guard = User::factory()->create([
            'role' => 'guard',
            'username' => 'guard.user',
        ]);

        $this
            ->actingAs($guard)
            ->postJson(route('push-notifications.subscribe'), $this->subscriptionPayload())
            ->assertForbidden();
    }

    public function test_supervisor_can_fetch_web_push_config_status(): void
    {
        config([
            'services.webpush.vapid_subject' => 'mailto:noreply@slsubcpatrol.site',
            'services.webpush.vapid_public_key' => 'test-public-key',
            'services.webpush.vapid_private_key' => 'test-private-key',
        ]);

        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $this
            ->actingAs($supervisor)
            ->getJson(route('push-notifications.config'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('public_key', 'test-public-key')
            ->assertJsonPath('missing', []);
    }

    public function test_web_push_config_reports_missing_server_values(): void
    {
        config([
            'services.webpush.vapid_subject' => 'mailto:noreply@slsubcpatrol.site',
            'services.webpush.vapid_public_key' => null,
            'services.webpush.vapid_private_key' => null,
        ]);

        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $this
            ->actingAs($supervisor)
            ->getJson(route('push-notifications.config'))
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('missing.0', 'WEBPUSH_VAPID_PUBLIC_KEY')
            ->assertJsonPath('missing.1', 'WEBPUSH_VAPID_PRIVATE_KEY');
    }

    public function test_rfid_scan_issue_dispatches_supervisor_web_push(): void
    {
        $notifier = Mockery::mock(WebPushNotifier::class);
        $notifier
            ->shouldReceive('sendPatrolScanIssue')
            ->once()
            ->with(Mockery::on(fn (PatrolLog $patrolLog) => $patrolLog->status === 'invalid'))
            ->andReturn(1);

        $this->app->instance(WebPushNotifier::class, $notifier);

        $this
            ->postJson(route('api.rfid-scan'), [
                'rfid_uid' => 'UNKNOWN123',
                'device_uid' => 'ESP32-UNKNOWN',
            ])
            ->assertUnprocessable();
    }

    private function subscriptionPayload(string $endpoint = 'https://example.com/push/subscription-1'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => str_repeat('A', 88),
                'auth' => str_repeat('B', 24),
            ],
            'contentEncoding' => 'aes128gcm',
        ];
    }
}

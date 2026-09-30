<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_store_browser_push_subscription(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $endpoint = 'https://updates.push.services.mozilla.com/wpush/v2/example-subscription';

        $this
            ->actingAs($supervisor)
            ->postJson(route('push-subscriptions.store'), [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => str_repeat('a', 88),
                    'auth' => str_repeat('b', 24),
                ],
                'contentEncoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $supervisor->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_guard_cannot_store_browser_push_subscription(): void
    {
        $guard = User::factory()->create(['role' => 'guard']);

        $this
            ->actingAs($guard)
            ->postJson(route('push-subscriptions.store'), [
                'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/guard-subscription',
                'keys' => [
                    'p256dh' => str_repeat('a', 88),
                    'auth' => str_repeat('b', 24),
                ],
            ])
            ->assertForbidden();
    }

    public function test_supervisor_sees_enable_push_button_when_vapid_key_is_configured(): void
    {
        config(['webpush.vapid.public_key' => 'test-public-key']);

        $supervisor = User::factory()->create(['role' => 'admin']);
        $guard = User::factory()->create(['role' => 'guard']);

        $this
            ->actingAs($supervisor)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Browser Push')
            ->assertSee('webPushManager', false);

        $this
            ->actingAs($guard)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Browser Push')
            ->assertDontSee('webPushManager', false);
    }
}

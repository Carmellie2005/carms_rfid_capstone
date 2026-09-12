<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_is_disabled(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertNotFound();
    }

    public function test_reset_password_link_request_is_disabled(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'guard@example.com']);

        $response->assertNotFound();
    }

    public function test_reset_password_screen_is_disabled(): void
    {
        $response = $this->get('/reset-password/example-token');

        $response->assertNotFound();
    }

    public function test_password_reset_submit_is_disabled(): void
    {
        $response = $this->post('/reset-password', [
            'token' => 'example-token',
            'email' => 'guard@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
    }
}

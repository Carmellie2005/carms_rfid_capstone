<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_authenticate_using_a_username(): void
    {
        $user = User::factory()->create([
            'username' => 'guard.one',
            'role' => 'guard',
        ]);

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('patrol.scan'));
    }

    public function test_users_can_authenticate_using_an_email_style_username(): void
    {
        $user = User::factory()->create([
            'email' => 'actual.email@example.com',
            'username' => 'email.username@example.com',
            'role' => 'guard',
        ]);

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('patrol.scan'));
    }

    public function test_authenticated_guard_opening_login_is_redirected_to_scan_checkpoint(): void
    {
        $guard = User::factory()->create([
            'role' => 'guard',
        ]);

        $response = $this->actingAs($guard)->get('/login');

        $response->assertRedirect(route('patrol.scan'));
    }

    public function test_authenticated_admin_opening_login_is_redirected_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get('/login');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_screen_does_not_show_remember_me(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertDontSee('Remember me');
    }

    public function test_remember_me_input_is_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_login_does_not_set_a_recaller_cookie(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'We could not sign you in. Check your account ID and password, then try again.',
            ]);
    }

    public function test_expired_session_redirects_to_login_with_clear_message(): void
    {
        $request = Request::create('/patrol/scan', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $exceptionResponse = $this->app
            ->make(\App\Exceptions\Handler::class)
            ->render($request, new TokenMismatchException());

        $this->assertSame(302, $exceptionResponse->getStatusCode());
        $this->assertSame(route('login'), $exceptionResponse->headers->get('Location'));
        $this->assertSame('Your session expired. Please log in again.', session('status'));
    }

    public function test_login_is_locked_for_thirty_seconds_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $lockoutSeconds = session('login_lockout_seconds');

        $this->assertIsInt($lockoutSeconds);
        $this->assertGreaterThan(0, $lockoutSeconds);
        $this->assertLessThanOrEqual(30, $lockoutSeconds);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

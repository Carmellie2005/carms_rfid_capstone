<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_greets_the_signed_in_supervisor_by_name(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Security Supervisor',
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Welcome, Security Supervisor')
            ->assertDontSee('Command Dashboard');
    }
}

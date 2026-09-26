<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_can_complete_tutorial(): void
    {
        $guard = User::factory()->create([
            'role' => 'guard',
            'guard_tutorial_completed_at' => null,
        ]);

        $this
            ->actingAs($guard)
            ->postJson(route('guard.tutorial.complete'))
            ->assertOk()
            ->assertJson([
                'completed' => true,
            ]);

        $this->assertNotNull($guard->refresh()->guard_tutorial_completed_at);
    }

    public function test_supervisor_cannot_complete_guard_tutorial(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
        ]);

        $this
            ->actingAs($supervisor)
            ->postJson(route('guard.tutorial.complete'))
            ->assertForbidden();
    }
}

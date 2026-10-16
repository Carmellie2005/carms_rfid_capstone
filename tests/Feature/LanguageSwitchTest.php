<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_switch_to_tagalog(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->from(route('dashboard'))
            ->post(route('language.update'), ['locale' => 'fil']);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'fil');

        $this
            ->actingAs($supervisor)
            ->withSession(['locale' => 'fil'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pangunahing Menu')
            ->assertSee('Mga Profile ng Guard');
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $this
            ->actingAs($supervisor)
            ->from(route('dashboard'))
            ->post(route('language.update'), ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');
    }
}

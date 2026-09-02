<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckpointManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_create_checkpoint(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('checkpoints.store'), [
                'code' => 'cp-new',
                'name' => 'New Checkpoint',
                'location' => 'Main Gate',
                'device_uid' => 'esp32-new',
                'status' => 'active',
                'description' => 'Main gate reader.',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('checkpoints.index'));

        $checkpoint = Checkpoint::where('code', 'CP-NEW')->firstOrFail();

        $this->assertSame('ESP32-NEW', $checkpoint->device_uid);
    }

    public function test_checkpoint_index_uses_modal_for_new_checkpoint(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('checkpoints.index'));

        $response
            ->assertOk()
            ->assertSee('x-on:click="$dispatch(\'open-create-checkpoint\')"', false)
            ->assertSee('Create Checkpoint')
            ->assertSee('Checkpoints')
            ->assertSee('Checkpoint Management')
            ->assertDontSee('Checkpoint List')
            ->assertDontSee(route('checkpoints.create'), false);
    }
}

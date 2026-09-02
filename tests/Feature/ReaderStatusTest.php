<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_status_page_does_not_show_manage_checkpoints_button(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('readers.index'));

        $response
            ->assertOk()
            ->assertSee('RFID Reader Status')
            ->assertDontSee('Manage Checkpoints');
    }
}

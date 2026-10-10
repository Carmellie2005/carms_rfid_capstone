<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
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

    public function test_reader_status_is_shown_in_supervisor_navigation(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Reader Status')
            ->assertSee(route('readers.index'), false);
    }

    public function test_reader_heartbeat_stores_device_diagnostics(): void
    {
        $checkpoint = Checkpoint::create([
            'code' => 'CP-CAN-01',
            'name' => 'Campus Canteen',
            'location' => 'Canteen Area',
            'device_uid' => 'ESP32-CAN-01',
            'status' => 'active',
        ]);

        $response = $this->postJson(route('api.rfid-heartbeat'), [
            'device_uid' => 'ESP32-CAN-01',
            'wifi_status' => 'connected',
            'wifi_rssi' => -55,
            'local_ip' => '192.168.1.50',
            'rfid_status' => 'online',
            'lcd_status' => 'online',
            'buzzer_status' => 'ready',
            'firmware' => 'checkpoint-diagnostics-v1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'online');

        $checkpoint->refresh();

        $this->assertSame('connected', $checkpoint->reader_diagnostics['wifi_status']);
        $this->assertSame(-55, $checkpoint->reader_diagnostics['wifi_rssi']);
        $this->assertSame('online', $checkpoint->reader_diagnostics['rfid_status']);

        $supervisor = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($supervisor)
            ->get(route('readers.index'))
            ->assertOk()
            ->assertSee('Device Diagnostics')
            ->assertSee('-55 dBm')
            ->assertSee('No device errors reported.');
    }
}

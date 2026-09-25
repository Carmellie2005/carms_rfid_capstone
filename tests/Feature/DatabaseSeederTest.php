<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_supervisor_can_use_default_password_without_sample_guards(): void
    {
        $this->seed();

        $supervisor = User::where('email', 'security.supervisor@campuspatrol.local')->firstOrFail();

        $this->assertSame('admin', $supervisor->role);
        $this->assertSame('supervisor', $supervisor->username);
        $this->assertTrue(Hash::check('password123', $supervisor->password));
        $this->assertFalse($supervisor->must_change_password);
        $this->assertSame(0, Guard::count());
        $this->assertFalse(User::where('role', 'guard')->exists());
    }

    public function test_seeder_does_not_reset_existing_supervisor_password_by_default(): void
    {
        $this->seed();

        $supervisor = User::where('username', 'supervisor')->firstOrFail();
        $supervisor->forceFill([
            'password' => Hash::make('my-own-password'),
            'must_change_password' => true,
        ])->save();

        $this->seed();

        $supervisor->refresh();

        $this->assertTrue(Hash::check('my-own-password', $supervisor->password));
        $this->assertFalse($supervisor->must_change_password);
    }

    public function test_seeder_can_reset_existing_supervisor_password_when_enabled(): void
    {
        $this->seed();

        $supervisor = User::where('username', 'supervisor')->firstOrFail();
        $supervisor->forceFill([
            'password' => Hash::make('my-own-password'),
        ])->save();

        putenv('DEFAULT_SUPERVISOR_PASSWORD=new-hosted-password');
        putenv('RESET_DEFAULT_SUPERVISOR_PASSWORD=true');
        $_ENV['DEFAULT_SUPERVISOR_PASSWORD'] = 'new-hosted-password';
        $_ENV['RESET_DEFAULT_SUPERVISOR_PASSWORD'] = 'true';
        $_SERVER['DEFAULT_SUPERVISOR_PASSWORD'] = 'new-hosted-password';
        $_SERVER['RESET_DEFAULT_SUPERVISOR_PASSWORD'] = 'true';

        try {
            $this->seed();

            $this->assertTrue(Hash::check('new-hosted-password', $supervisor->refresh()->password));
        } finally {
            putenv('DEFAULT_SUPERVISOR_PASSWORD');
            putenv('RESET_DEFAULT_SUPERVISOR_PASSWORD');
            unset(
                $_ENV['DEFAULT_SUPERVISOR_PASSWORD'],
                $_ENV['RESET_DEFAULT_SUPERVISOR_PASSWORD'],
                $_SERVER['DEFAULT_SUPERVISOR_PASSWORD'],
                $_SERVER['RESET_DEFAULT_SUPERVISOR_PASSWORD'],
            );
        }
    }

    public function test_seeder_replaces_ag_with_guard_house_checkpoint(): void
    {
        Checkpoint::create([
            'code' => 'CP-AG-01',
            'name' => 'AG',
            'location' => 'AG',
            'device_uid' => 'ESP32-AG-01',
            'status' => 'active',
        ]);

        $this->seed();

        $this->assertDatabaseHas('checkpoints', [
            'code' => 'CP-GH-01',
            'name' => 'Guard House',
            'location' => 'GH',
            'device_uid' => 'ESP32-GH-01',
            'status' => 'active',
        ]);

        $this->assertSame('inactive', Checkpoint::where('code', 'CP-AG-01')->firstOrFail()->status);
        $this->assertFalse(Checkpoint::where('code', 'CP-AG-01')->where('status', 'active')->exists());
    }

    public function test_seeder_uses_updated_checkpoint_names_and_devices(): void
    {
        $this->seed();

        $this->assertDatabaseHas('checkpoints', [
            'code' => 'CP-IT-01',
            'name' => 'BITS',
            'location' => 'BITS',
            'device_uid' => 'ESP32-IT-01',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('checkpoints', [
            'code' => 'CP-FI-01',
            'name' => 'Tilapia Hatchery',
            'location' => 'Tilapia Hatchery',
            'device_uid' => 'ESP32-TH-01',
            'status' => 'active',
        ]);
    }
}

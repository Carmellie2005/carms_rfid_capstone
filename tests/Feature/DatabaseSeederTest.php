<?php

namespace Tests\Feature;

use App\Models\Guard;
use App\Models\Checkpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_online_accounts_can_use_default_password(): void
    {
        $this->seed();

        $supervisor = User::where('email', 'security.supervisor@campuspatrol.local')->firstOrFail();
        $guardUser = User::where('username', 'carmela.bihay.hernandez')->firstOrFail();
        $guard = Guard::where('employee_no', 'TEST-01')->firstOrFail();

        $this->assertSame('admin', $supervisor->role);
        $this->assertTrue(Hash::check('password123', $supervisor->password));
        $this->assertFalse($supervisor->must_change_password);

        $this->assertSame('guard', $guardUser->role);
        $this->assertSame('carmela.bihay.hernandez@guard.local', $guardUser->email);
        $this->assertTrue(Hash::check('password123', $guardUser->password));
        $this->assertTrue($guardUser->must_change_password);

        $this->assertSame($guardUser->id, $guard->user_id);
        $this->assertSame('F33C8D37', $guard->rfid_uid);
        $this->assertSame('Night Shift', $guard->shift);
        $this->assertSame('active', $guard->status);
    }

    public function test_seeder_does_not_reset_existing_guard_password_requirement(): void
    {
        $this->seed();

        $guardUser = User::where('username', 'carmela.bihay.hernandez')->firstOrFail();
        $guardUser->forceFill([
            'password' => Hash::make('my-own-password'),
            'must_change_password' => false,
        ])->save();

        $this->seed();

        $guardUser->refresh();

        $this->assertTrue(Hash::check('my-own-password', $guardUser->password));
        $this->assertFalse($guardUser->must_change_password);
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

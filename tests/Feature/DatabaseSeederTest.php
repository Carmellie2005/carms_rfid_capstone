<?php

namespace Tests\Feature;

use App\Models\Guard;
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
}

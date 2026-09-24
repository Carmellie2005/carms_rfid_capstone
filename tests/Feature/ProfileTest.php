<?php

namespace Tests\Feature;

use App\Models\Guard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertDontSee('Update Password')
            ->assertDontSee('Before you continue')
            ->assertDontSee('Delete Account');
    }

    public function test_supervisor_does_not_see_guard_temporary_password_modal(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertDontSee('Before you continue')
            ->assertDontSee('Hi,')
            ->assertDontSee('Update Password');
    }

    public function test_guard_with_temporary_password_sees_password_change_guide(): void
    {
        $user = User::factory()->create([
            'name' => 'Carmela Hernandez',
            'role' => 'guard',
            'username' => 'temporary.password',
            'must_change_password' => true,
        ]);

        Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-TEMP',
            'name' => 'Temporary Password Guard',
            'rfid_uid' => 'RFID-TEMP',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('Hi, Carmela!')
            ->assertSee('Before you continue, set your own password for this guard account.')
            ->assertSee('Use 8+ characters and keep it private.')
            ->assertSee(route('password.temporary.update'), false)
            ->assertSee('Save Password')
            ->assertSee('aria-label="Show new password"', false)
            ->assertSee('aria-label="Show confirm password"', false)
            ->assertDontSee('temporary_password_current_password', false)
            ->assertSee('Update Password')
            ->assertDontSee('Delete Account');
    }

    public function test_guard_profile_page_displays_guard_information(): void
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'guard.profile',
            'birthday' => '1998-04-12',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-TEST',
            'name' => 'Test Guard',
            'email' => 'guard.profile@example.com',
            'phone' => '09171234567',
            'rfid_uid' => 'RFID-TEST',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Guard Profile Settings')
            ->assertSee('Security Guard')
            ->assertSee('Security and Safety Services Office')
            ->assertSee('SG-TEST')
            ->assertSee('RFID-TEST')
            ->assertSee('Night Shift')
            ->assertSee('Apr 12, 1998')
            ->assertSee('Update Password')
            ->assertSee('aria-label="Show current password"', false)
            ->assertSee('aria-label="Show new password"', false)
            ->assertSee('aria-label="Show confirm password"', false);
    }

    public function test_missing_profile_photo_falls_back_to_guard_icon(): void
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'missing.photo',
            'profile_photo_path' => 'profile-photos/missing-render-file.jpg',
        ]);

        Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-PHOTO',
            'name' => 'Missing Photo Guard',
            'rfid_uid' => 'RFID-PHOTO',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('images/user-icons/guard-account.png')
            ->assertDontSee('storage/profile-photos/missing-render-file.jpg');
    }

    public function test_completed_guard_profile_displays_one_hundred_percent_completion(): void
    {
        $user = User::factory()->create([
            'name' => 'Complete Guard',
            'username' => 'complete.guard',
            'role' => 'guard',
            'phone' => '09170000000',
            'profile_photo_path' => 'profile-photos/complete.jpg',
        ]);

        Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-COMPLETE',
            'name' => 'Complete Guard',
            'email' => 'complete.guard@example.com',
            'phone' => '09170000000',
            'rfid_uid' => 'RFID-COMPLETE',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Complete')
            ->assertDontSee('Face Registration')
            ->assertDontSee('Face Samples');
    }

    public function test_guard_profile_page_does_not_show_face_registration_controls(): void
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'missing.face',
        ]);

        Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-MISSING',
            'name' => 'Missing Face Guard',
            'rfid_uid' => 'RFID-MISSING',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertDontSee('Capture 5 Live Face Samples')
            ->assertDontSee('Face Registration')
            ->assertDontSee('face_registration_captures', false)
            ->assertDontSee('Take Photo')
            ->assertDontSee('registrationPhotoInput')
            ->assertDontSee('face_registration_image');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'username' => 'old.username',
            'phone' => '09170000000',
            'birthday' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'test.user',
                'email' => 'test@example.com',
                'phone' => '09171234567',
                'birthday' => '1997-05-21',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test.user', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame('1997-05-21', $user->birthday->toDateString());
        $this->assertNull($user->email_verified_at);
    }

    public function test_supervisor_profile_page_displays_birthday(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'birthday.supervisor',
            'birthday' => '1995-02-14',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Birthday')
            ->assertSee('Feb 14, 1995');
    }

    public function test_guard_can_update_birthday_from_profile_settings(): void
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'birthday.guard',
            'birthday' => null,
        ]);

        Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-BDAY',
            'name' => 'Birthday Guard',
            'rfid_uid' => 'RFID-BDAY',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => '09171234567',
                'birthday' => '1999-09-12',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('1999-09-12', $user->birthday->toDateString());
    }

    public function test_profile_page_does_not_show_profile_photo_upload_controls(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
            'profile_photo_path' => 'profile-photos/current.jpg',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertDontSee('Profile Picture')
            ->assertDontSee('name="profile_photo"', false)
            ->assertDontSee('Remove current profile picture')
            ->assertDontSee('JPG, PNG, WEBP');
    }

    public function test_profile_photo_update_fields_are_ignored(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/current.jpg', 'current photo');

        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
            'profile_photo_path' => 'profile-photos/current.jpg',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_photo' => UploadedFile::fake()->image('supervisor.jpg'),
                'remove_profile_photo' => '1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('profile-photos/current.jpg', $user->refresh()->profile_photo_path);
        Storage::disk('public')->assertExists('profile-photos/current.jpg');
        $this->assertCount(1, Storage::disk('public')->allFiles('profile-photos'));
    }


    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }


}

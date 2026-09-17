<?php

namespace Tests\Feature;

use App\Models\Guard;
use App\Models\User;
use App\Support\FaceVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.face_verification' => true]);
    }

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
            ->assertDontSee('Guard Account Security')
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
            ->assertDontSee('Guard Account Security')
            ->assertDontSee('Change Temporary Password')
            ->assertDontSee('Update Password');
    }

    public function test_guard_with_temporary_password_sees_password_change_guide(): void
    {
        $user = User::factory()->create([
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
            ->assertSee('Guard Account Security')
            ->assertSee('Change Temporary Password')
            ->assertSee('At least 8 characters.')
            ->assertSee('Avoid your name, birthday, or employee number.')
            ->assertSee('temporary_password_current_password', false)
            ->assertSee('Save New Password')
            ->assertSee('aria-label="Show current password"', false)
            ->assertSee('aria-label="Show new password"', false)
            ->assertSee('aria-label="Show confirm password"', false)
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
            'face_reference' => 'test-guard',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $this->completeFaceRegistration($guard);

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
            ->assertSee('Completed')
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

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-COMPLETE',
            'name' => 'Complete Guard',
            'email' => 'complete.guard@example.com',
            'phone' => '09170000000',
            'rfid_uid' => 'RFID-COMPLETE',
            'face_reference' => 'complete-guard',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $this->completeFaceRegistration($guard);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Complete')
            ->assertSee('Face Registration')
            ->assertSee('5 Face Samples Processed');
    }

    public function test_guard_profile_page_displays_live_liveness_registration_when_face_is_missing(): void
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
            ->assertSee('Capture 5 Live Face Samples')
            ->assertSee('Front neutral')
            ->assertSee('Front smile')
            ->assertSee('Slight left turn')
            ->assertSee('Slight right turn')
            ->assertSee('Normal or low-light')
            ->assertSee('Complete guided action')
            ->assertSee('Light Assist')
            ->assertSee('Capture sample')
            ->assertSee('face_registration_captures', false)
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

    public function test_guard_can_complete_live_face_registration_once(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'face.guard',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-FACE',
            'name' => 'Face Guard',
            'rfid_uid' => 'RFID-FACE',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'face_liveness_confirmed' => '1',
                ...$this->faceRegistrationPayload(),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame(FaceVerification::requiredRegistrationSampleCount(), $guard->faceDescriptors()->count());

        $faceRegistration = $guard->faceDescriptors()->where('capture_type', 'front_neutral')->first();
        $this->assertTrue($faceRegistration->is_primary);
        $this->assertSame(array_fill(0, 128, 0.2), $faceRegistration->descriptor);
        Storage::disk('public')->assertExists($faceRegistration->image_path);

        foreach (array_keys(FaceVerification::registrationSampleTypes()) as $type) {
            $this->assertDatabaseHas('guard_face_descriptors', [
                'guard_id' => $guard->id,
                'capture_type' => $type,
            ]);
        }
    }

    public function test_guard_cannot_complete_face_registration_without_liveness_check(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'no.liveness',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-LIVE',
            'name' => 'Liveness Guard',
            'rfid_uid' => 'RFID-LIVE',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                ...$this->faceRegistrationPayload(),
            ]);

        $response
            ->assertSessionHasErrors('face_liveness_confirmed')
            ->assertRedirect('/profile');

        $this->assertSame(0, $guard->faceDescriptors()->count());
    }

    public function test_guard_cannot_complete_face_registration_with_liveness_but_without_capture(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'no.capture',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-NOCAP',
            'name' => 'No Capture Guard',
            'rfid_uid' => 'RFID-NOCAP',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'face_liveness_confirmed' => '1',
                'face_descriptors' => $this->faceRegistrationDescriptors(),
            ]);

        $response
            ->assertSessionHasErrors('face_registration_captures.front_neutral')
            ->assertRedirect('/profile');

        $this->assertSame(0, $guard->faceDescriptors()->count());
    }

    public function test_guard_cannot_replace_completed_face_registration(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'locked.guard',
        ]);

        $guard = Guard::create([
            'user_id' => $user->id,
            'employee_no' => 'SG-LOCKED',
            'name' => 'Locked Guard',
            'rfid_uid' => 'RFID-LOCKED',
            'shift' => 'Night Shift',
            'status' => 'active',
        ]);

        $this->completeFaceRegistration($guard);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'face_liveness_confirmed' => '1',
                ...$this->faceRegistrationPayload(0.3),
            ]);

        $response
            ->assertSessionHasErrors('face_registration_captures')
            ->assertRedirect('/profile');

        $this->assertSame(FaceVerification::requiredRegistrationSampleCount(), $guard->faceDescriptors()->count());
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

    private function completeFaceRegistration(Guard $guard, float $descriptorValue = 0.1): void
    {
        foreach (array_keys(FaceVerification::registrationSampleTypes()) as $index => $type) {
            $guard->faceDescriptors()->create([
                'image_path' => "guard-faces/{$guard->id}/{$type}.jpg",
                'descriptor' => array_fill(0, 128, $descriptorValue),
                'model_name' => 'face-api.js',
                'capture_type' => $type,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function faceRegistrationPayload(float $descriptorValue = 0.2): array
    {
        return [
            'face_registration_captures' => collect(FaceVerification::registrationSampleTypes())
                ->mapWithKeys(fn ($label, $type) => [$type => $this->liveFaceCapture()])
                ->all(),
            'face_descriptors' => $this->faceRegistrationDescriptors($descriptorValue),
        ];
    }

    private function faceRegistrationDescriptors(float $descriptorValue = 0.2): array
    {
        return collect(FaceVerification::registrationSampleTypes())
            ->mapWithKeys(fn ($label, $type) => [$type => json_encode(array_fill(0, 128, $descriptorValue))])
            ->all();
    }

    private function liveFaceCapture(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    }
}

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
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_guard_profile_page_displays_guard_information(): void
    {
        $user = User::factory()->create([
            'role' => 'guard',
            'username' => 'guard.profile',
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

        $guard->faceDescriptors()->create([
            'image_path' => 'guard-faces/1/test-guard.jpg',
            'descriptor' => array_fill(0, 128, 0.1),
            'model_name' => 'face-api.js',
            'is_primary' => true,
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
            ->assertSee('Completed');
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

        $guard->faceDescriptors()->create([
            'image_path' => 'guard-faces/1/complete-guard.jpg',
            'descriptor' => array_fill(0, 128, 0.1),
            'model_name' => 'face-api.js',
            'is_primary' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Complete')
            ->assertSee('Face Registration')
            ->assertSee('Face Data Processed');
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
            ->assertSee('Live Camera Registration')
            ->assertSee('Liveness Check')
            ->assertSee('Random action challenge')
            ->assertSee('smile or turn head slightly')
            ->assertSee('Complete random challenge')
            ->assertSee('Take Photo')
            ->assertSee('Capture reference')
            ->assertDontSee('face_registration_image');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'username' => 'old.username',
            'phone' => '09170000000',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'test.user',
                'email' => 'test@example.com',
                'phone' => '09171234567',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test.user', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('09171234567', $user->phone);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'supervisor',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_photo' => UploadedFile::fake()->image('supervisor.jpg'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_profile_photo_can_be_removed(): void
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
                'remove_profile_photo' => '1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('public')->assertMissing('profile-photos/current.jpg');
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
                'face_registration_capture' => $this->liveFaceCapture(),
                'face_liveness_confirmed' => '1',
                'face_descriptors' => [
                    json_encode(array_fill(0, 128, 0.2)),
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame(1, $guard->faceDescriptors()->count());

        $faceRegistration = $guard->faceDescriptors()->first();
        $this->assertTrue($faceRegistration->is_primary);
        $this->assertSame(array_fill(0, 128, 0.2), $faceRegistration->descriptor);
        Storage::disk('public')->assertExists($faceRegistration->image_path);
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
                'face_registration_capture' => $this->liveFaceCapture(),
                'face_descriptors' => [
                    json_encode(array_fill(0, 128, 0.2)),
                ],
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
                'face_descriptors' => [
                    json_encode(array_fill(0, 128, 0.2)),
                ],
            ]);

        $response
            ->assertSessionHasErrors('face_registration_capture')
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

        $guard->faceDescriptors()->create([
            'image_path' => 'guard-faces/1/locked.jpg',
            'descriptor' => array_fill(0, 128, 0.1),
            'model_name' => 'face-api.js',
            'is_primary' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'face_registration_capture' => $this->liveFaceCapture(),
                'face_liveness_confirmed' => '1',
                'face_descriptors' => [
                    json_encode(array_fill(0, 128, 0.3)),
                ],
            ]);

        $response
            ->assertSessionHasErrors('face_registration_capture')
            ->assertRedirect('/profile');

        $this->assertSame(1, $guard->faceDescriptors()->count());
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

    private function liveFaceCapture(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    }
}

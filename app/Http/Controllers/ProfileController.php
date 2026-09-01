<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->loadMissing(['guardProfile.faceDescriptors']);

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing(['guardProfile.faceDescriptors']);
        $data = $request->validated();
        $removeProfilePhoto = $request->boolean('remove_profile_photo');
        $profilePhotoChanged = false;
        $profilePhotoRemoved = false;
        $wantsFaceRegistration = filled($request->input('face_registration_capture'))
            || filled($request->input('face_descriptors.0'));
        $faceRegistration = $wantsFaceRegistration
            ? $this->validatedGuardFaceRegistration(
                $user,
                $request->input('face_descriptors.0'),
                $request->input('face_registration_capture'),
                $request->boolean('face_liveness_confirmed'),
            )
            : null;

        unset(
            $data['profile_photo'],
            $data['remove_profile_photo'],
            $data['face_registration_capture'],
            $data['face_liveness_confirmed'],
            $data['face_descriptors'],
        );

        if (array_key_exists('username', $data)) {
            $data['username'] = filled($data['username']) ? Str::lower(trim($data['username'])) : null;
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = filled($data['phone']) ? trim($data['phone']) : null;
        }

        if ($request->hasFile('profile_photo')) {
            $previousPhoto = $user->profile_photo_path;
            $profilePhotoChanged = true;

            $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');

            if ($previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }
        } elseif ($removeProfilePhoto && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);

            $data['profile_photo_path'] = null;
            $profilePhotoRemoved = true;
        }

        DB::transaction(function () use ($user, $data, $faceRegistration) {
            $user->fill($data);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($faceRegistration) {
                $this->storeGuardFaceRegistration($faceRegistration);
            }
        });

        AuditLogger::record('profile_updated', 'Profile settings updated.', $user, [
            'profile_photo_changed' => $profilePhotoChanged,
            'profile_photo_removed' => $profilePhotoRemoved,
            'face_registration_completed' => (bool) $faceRegistration,
        ]);

        if ($faceRegistration) {
            AuditLogger::record('face_registration_completed', 'Guard live face registration completed.', $faceRegistration['guard'], [
                'guard_id' => $faceRegistration['guard']->id,
                'employee_no' => $faceRegistration['guard']->employee_no,
            ]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        AuditLogger::record('account_deleted', 'User account deleted.', $user, [
            'role' => $user->role,
            'email' => $user->email,
        ]);

        Auth::logout();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function storeGuardFaceRegistration(array $faceRegistration): void
    {
        $guard = $faceRegistration['guard'];
        $image = $faceRegistration['image'];
        $this->deleteIncompleteFaceDescriptors($guard);

        $path = 'guard-faces/'.$guard->id.'/live-registration-'.Str::uuid().'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        $guard->faceDescriptors()->create([
            'descriptor' => $faceRegistration['descriptor'],
            'model_name' => 'face-api.js',
            'image_path' => $path,
            'is_primary' => true,
        ]);
    }

    private function validatedGuardFaceRegistration(User $user, ?string $descriptorJson, ?string $captureDataUrl, bool $livenessConfirmed): array
    {
        if ($user->role !== 'guard' || ! $user->guardProfile) {
            throw ValidationException::withMessages([
                'face_registration_capture' => 'Face registration is only available for linked guard accounts.',
            ]);
        }

        $guard = $user->guardProfile;

        if ($this->hasProcessedFaceRegistration($guard)) {
            throw ValidationException::withMessages([
                'face_registration_capture' => 'Face registration has already been completed for this guard.',
            ]);
        }

        if (! $livenessConfirmed) {
            throw ValidationException::withMessages([
                'face_liveness_confirmed' => 'Complete the random liveness challenge before saving face registration.',
            ]);
        }

        $image = $this->imageFromCaptureDataUrl($captureDataUrl);

        if (! $image) {
            throw ValidationException::withMessages([
                'face_registration_capture' => 'Open the camera and capture a live face before saving.',
            ]);
        }

        $descriptor = $this->descriptorFromJson($descriptorJson);

        if (! $descriptor) {
            throw ValidationException::withMessages([
                'face_registration_capture' => 'Face data is not ready. Capture a clear front-facing face and wait for processing to finish.',
            ]);
        }

        return [
            'guard' => $guard,
            'descriptor' => $descriptor,
            'image' => $image,
        ];
    }

    private function hasProcessedFaceRegistration($guard): bool
    {
        return $guard->faceDescriptors()
            ->get()
            ->contains(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128);
    }

    private function deleteIncompleteFaceDescriptors($guard): void
    {
        $guard->faceDescriptors()
            ->get()
            ->reject(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128)
            ->each(function ($sample) {
                if ($sample->image_path) {
                    Storage::disk('public')->delete($sample->image_path);
                }

                $sample->delete();
            });
    }

    private function imageFromCaptureDataUrl(?string $captureDataUrl): ?array
    {
        if (! filled($captureDataUrl)) {
            return null;
        }

        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $captureDataUrl, $matches)) {
            return null;
        }

        $contents = base64_decode($matches[2], true);

        if ($contents === false || getimagesizefromstring($contents) === false) {
            return null;
        }

        return [
            'extension' => $matches[1] === 'jpeg' ? 'jpg' : $matches[1],
            'contents' => $contents,
        ];
    }

    private function descriptorFromJson(?string $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        $descriptor = json_decode($value, true);

        if (! is_array($descriptor) || count($descriptor) !== 128 || ! collect($descriptor)->every(fn ($item) => is_numeric($item))) {
            return null;
        }

        return array_map(static fn ($item) => round((float) $item, 8), array_values($descriptor));
    }
}

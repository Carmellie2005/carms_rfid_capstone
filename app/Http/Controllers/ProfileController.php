<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FaceVerification;
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
        $faceRegistrationCaptures = $request->input('face_registration_captures', []);
        $faceRegistrationDescriptors = $request->input('face_descriptors', []);
        $wantsFaceRegistration = FaceVerification::enabled()
            && (
                collect($faceRegistrationCaptures)->filter(fn ($value) => filled($value))->isNotEmpty()
                || collect($faceRegistrationDescriptors)->filter(fn ($value) => filled($value))->isNotEmpty()
            );
        $faceRegistration = $wantsFaceRegistration
            ? $this->validatedGuardFaceRegistration(
                $user,
                is_array($faceRegistrationDescriptors) ? $faceRegistrationDescriptors : [],
                is_array($faceRegistrationCaptures) ? $faceRegistrationCaptures : [],
                $request->boolean('face_liveness_confirmed'),
            )
            : null;

        unset(
            $data['face_registration_capture'],
            $data['face_registration_captures'],
            $data['face_liveness_confirmed'],
            $data['face_descriptors'],
        );

        if (array_key_exists('username', $data)) {
            $data['username'] = filled($data['username']) ? Str::lower(trim($data['username'])) : null;
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = filled($data['phone']) ? trim($data['phone']) : null;
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
        $this->deleteIncompleteFaceDescriptors($guard);

        foreach ($faceRegistration['samples'] as $sample) {
            $image = $sample['image'];
            $path = 'guard-faces/'.$guard->id.'/'.$sample['type'].'-'.Str::uuid().'.'.$image['extension'];
            Storage::disk('public')->put($path, $image['contents']);

            $guard->faceDescriptors()->create([
                'descriptor' => $sample['descriptor'],
                'model_name' => 'face-api.js',
                'image_path' => $path,
                'capture_type' => $sample['type'],
                'is_primary' => $sample['is_primary'],
            ]);
        }
    }

    private function validatedGuardFaceRegistration(User $user, array $descriptorInputs, array $captureInputs, bool $livenessConfirmed): array
    {
        if ($user->role !== 'guard' || ! $user->guardProfile) {
            throw ValidationException::withMessages([
                'face_registration_captures' => 'Face registration is only available for linked guard accounts.',
            ]);
        }

        $guard = $user->guardProfile;

        if ($this->hasProcessedFaceRegistration($guard)) {
            throw ValidationException::withMessages([
                'face_registration_captures' => 'Face registration has already been completed for this guard.',
            ]);
        }

        if (! $livenessConfirmed) {
            throw ValidationException::withMessages([
                'face_liveness_confirmed' => 'Complete all five live face registration samples before saving.',
            ]);
        }

        $samples = [];
        $errors = [];

        foreach (FaceVerification::registrationSampleTypes() as $type => $label) {
            $image = $this->imageFromCaptureDataUrl($captureInputs[$type] ?? null);
            $descriptor = $this->descriptorFromJson($descriptorInputs[$type] ?? null);

            if (! $image) {
                $errors["face_registration_captures.{$type}"] = "Capture the {$label} live face sample.";
            }

            if (! $descriptor) {
                $errors["face_descriptors.{$type}"] = "Face data is not ready for the {$label} sample.";
            }

            if ($image && $descriptor) {
                $samples[] = [
                    'type' => $type,
                    'label' => $label,
                    'descriptor' => $descriptor,
                    'image' => $image,
                    'is_primary' => $type === array_key_first(FaceVerification::registrationSampleTypes()),
                ];
            }
        }

        if ($errors !== [] || count($samples) !== FaceVerification::requiredRegistrationSampleCount()) {
            throw ValidationException::withMessages($errors ?: [
                'face_registration_captures' => 'Complete all five live face registration samples before saving.',
            ]);
        }

        return [
            'guard' => $guard,
            'samples' => $samples,
        ];
    }

    private function hasProcessedFaceRegistration($guard): bool
    {
        return FaceVerification::hasCompleteRegistration($guard->faceDescriptors()->get(['descriptor', 'capture_type']));
    }

    private function deleteIncompleteFaceDescriptors($guard): void
    {
        $guard->faceDescriptors()
            ->get()
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

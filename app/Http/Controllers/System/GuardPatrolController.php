<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ChecklistResponse;
use App\Models\Checkpoint;
use App\Models\FaceVerificationAttempt;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use App\Support\FaceVerification;
use App\Support\PatrolChecklist;
use App\Support\PatrolSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuardPatrolController extends Controller
{
    private const FACE_LIVENESS_SESSION_KEY = 'patrol_face_liveness_challenges';

    public function create(): View
    {
        $guardProfile = auth()->user()?->guardProfile;
        $patrolScheduleOpen = PatrolSchedule::isOpen();
        $faceVerificationEnabled = FaceVerification::enabled();
        $pendingPatrol = $guardProfile && $patrolScheduleOpen
            ? $this->latestPendingPatrolFor($guardProfile)
            : null;
        $pendingFaceAttempt = $faceVerificationEnabled && $pendingPatrol
            ? $this->latestVerifiedFaceAttemptFor($pendingPatrol)
            : null;
        $faceRegistrationComplete = ! $faceVerificationEnabled
            || ($guardProfile ? $this->hasCompletedFaceRegistration($guardProfile) : false);
        $pendingFaceLivenessChallenge = $faceVerificationEnabled && $pendingPatrol
            ? $this->livenessChallengeFor($pendingPatrol)
            : null;

        return view('system.patrols.scan', [
            'checkpoints' => Checkpoint::where('status', 'active')->orderBy('name')->get(),
            'guardProfile' => $guardProfile,
            'pendingPatrol' => $pendingPatrol,
            'pendingFaceVerified' => $pendingPatrol ? (! $faceVerificationEnabled || (bool) $pendingFaceAttempt) : false,
            'pendingFaceMatchDistance' => $pendingFaceAttempt?->match_distance,
            'pendingFaceLivenessChallenge' => $pendingFaceLivenessChallenge,
            'faceVerificationEnabled' => $faceVerificationEnabled,
            'faceRegistrationComplete' => $faceRegistrationComplete,
            'patrolScheduleOpen' => $patrolScheduleOpen,
            'patrolScheduleTestingMode' => PatrolSchedule::isTestingMode(),
            'patrolScheduleLabel' => PatrolSchedule::windowLabel(),
            'patrolScheduleMessage' => PatrolSchedule::isTestingMode() ? PatrolSchedule::testingNotice() : PatrolSchedule::closedMessage(),
            'patrolTestingNotice' => PatrolSchedule::testingNotice(),
            'patrolScheduleNextOpen' => PatrolSchedule::nextOpenAt()->format('M d, Y h:i A'),
        ]);
    }

    public function pendingScan(Request $request): JsonResponse
    {
        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return response()->json([
                'pending' => false,
                'message' => 'Signed-in account is not linked to a guard profile.',
            ], 403);
        }

        if (! PatrolSchedule::isOpen()) {
            return response()->json([
                'pending' => false,
                'message' => PatrolSchedule::closedMessage(),
                'patrol_window' => PatrolSchedule::windowLabel(),
                'testing_mode' => PatrolSchedule::isTestingMode(),
                'patrol_log' => null,
            ]);
        }

        $patrolLog = $this->latestPendingPatrolFor($guard);

        return response()->json([
            'pending' => (bool) $patrolLog,
            'patrol_window' => PatrolSchedule::windowLabel(),
            'testing_mode' => PatrolSchedule::isTestingMode(),
            'testing_notice' => PatrolSchedule::isTestingMode() ? PatrolSchedule::testingNotice() : null,
            'patrol_log' => $patrolLog ? $this->patrolLogPayload($patrolLog) : null,
        ]);
    }

    public function verifyFace(Request $request): JsonResponse
    {
        if (! FaceVerification::enabled()) {
            return response()->json([
                'verified' => false,
                'message' => 'Face verification is currently disabled for patrols.',
            ], 409);
        }

        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'face_capture' => ['required', 'string'],
            'captured_descriptor' => ['required', 'string'],
            'face_liveness_confirmed' => ['accepted'],
            'face_liveness_challenge' => ['required', 'string', Rule::in(FaceVerification::livenessChallenges())],
        ]);

        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return response()->json([
                'verified' => false,
                'message' => 'Signed-in account is not linked to a guard profile.',
            ], 403);
        }

        if (! PatrolSchedule::isOpen()) {
            return response()->json([
                'verified' => false,
                'message' => PatrolSchedule::closedMessage(),
            ], 409);
        }

        $patrolLog = PatrolLog::query()
            ->whereKey($data['patrol_log_id'])
            ->where('guard_id', $guard->id)
            ->where('rfid_status', 'valid')
            ->where('facial_status', 'pending')
            ->where('status', 'pending_face')
            ->first();

        if (! $patrolLog) {
            return response()->json([
                'verified' => false,
                'message' => 'No pending RFID scan is available for this guard. Please scan your card at the checkpoint again.',
            ], 409);
        }

        if (! $this->livenessChallengeMatches($patrolLog, $data['face_liveness_challenge'])) {
            return response()->json([
                'verified' => false,
                'status' => 'failed',
                'message' => 'The face liveness challenge expired. Restart face verification after the RFID scan.',
                'match_distance' => null,
                'match_threshold' => FaceVerification::matchThreshold(),
            ], 422);
        }

        $faceResult = $this->evaluateFaceVerification(
            $guard,
            $data['captured_descriptor'],
            $data['face_capture'],
            true,
            $data['face_liveness_challenge'],
        );

        if (! $faceResult['processable']) {
            return response()->json([
                'verified' => false,
                'status' => 'failed',
                'message' => $faceResult['message'],
                'match_distance' => $faceResult['match_distance'],
                'match_threshold' => FaceVerification::matchThreshold(),
            ], 422);
        }

        $this->recordFaceVerificationAttempt($patrolLog, $guard, $faceResult);

        return response()->json([
            'verified' => $faceResult['verified'],
            'status' => $faceResult['verified'] ? 'verified' : 'failed',
            'message' => $faceResult['message'],
            'match_distance' => $faceResult['match_distance'],
            'match_threshold' => FaceVerification::matchThreshold(),
        ], $faceResult['verified'] ? 200 : 422);
    }

    public function store(Request $request): RedirectResponse
    {
        $faceVerificationEnabled = FaceVerification::enabled();

        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'face_capture' => ['nullable', 'string'],
            'captured_descriptor' => ['nullable', 'string'],
            'face_liveness_confirmed' => ['nullable', 'boolean'],
            'face_liveness_challenge' => ['nullable', 'string', Rule::in(FaceVerification::livenessChallenges())],
            ...PatrolChecklist::validationRules(),
            'checklist_photos' => ['nullable', 'array', 'max:'.count(PatrolChecklist::fields())],
            'checklist_photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'has_incident' => ['nullable', 'boolean'],
            'incident_category' => ['nullable', 'required_if:has_incident,1', 'string', 'max:100', Rule::in(PatrolChecklist::incidentCategories())],
            'incident_priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'incident_description' => ['nullable', 'required_if:has_incident,1', 'string', 'max:3000'],
            'incident_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'incident_images' => ['nullable', 'array', 'max:3'],
            'incident_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'incident_camera_images' => ['nullable', 'array', 'max:3'],
            'incident_camera_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return back()->with('warning', 'Signed-in account is not linked to an active guard profile.');
        }

        if (! PatrolSchedule::isOpen()) {
            return back()
                ->withInput()
                ->with('warning', PatrolSchedule::closedMessage());
        }

        $patrolLog = PatrolLog::with('checkpoint')
            ->whereKey($data['patrol_log_id'])
            ->where('guard_id', $guard->id)
            ->where('rfid_status', 'valid')
            ->when(
                $faceVerificationEnabled,
                fn ($query) => $query->where('facial_status', 'pending')->where('status', 'pending_face'),
                fn ($query) => $query
                    ->whereIn('facial_status', ['pending', 'not_required'])
                    ->whereIn('status', ['pending_face', 'pending_checklist']),
            )
            ->first();

        if (! $patrolLog) {
            return back()->with('warning', 'No pending RFID scan is available for this guard. Please scan your card at the checkpoint again.');
        }

        $verifiedFaceAttempt = $faceVerificationEnabled
            ? $this->latestVerifiedFaceAttemptFor($patrolLog)
            : null;
        $faceResult = $faceVerificationEnabled && $verifiedFaceAttempt
            ? [
                'processable' => true,
                'verified' => true,
                'message' => 'Face verification already completed. Continue to the patrol checklist.',
                'captured_descriptor' => $verifiedFaceAttempt->captured_descriptor,
                'captured_image' => null,
                'match_distance' => $verifiedFaceAttempt->match_distance === null ? null : (float) $verifiedFaceAttempt->match_distance,
                'liveness_confirmed' => (bool) $verifiedFaceAttempt->liveness_confirmed_at,
                'liveness_challenge' => $verifiedFaceAttempt->liveness_challenge,
            ]
            : null;

        if ($faceVerificationEnabled && ! $verifiedFaceAttempt && ! $this->livenessChallengeMatches($patrolLog, $data['face_liveness_challenge'] ?? null)) {
            return back()
                ->withInput()
                ->with('warning', 'Complete the current random liveness challenge before submitting patrol verification.');
        }

        if ($faceVerificationEnabled) {
            $faceResult ??= $this->evaluateFaceVerification(
                $guard,
                $data['captured_descriptor'] ?? null,
                $data['face_capture'] ?? null,
                $request->boolean('face_liveness_confirmed'),
                $data['face_liveness_challenge'] ?? null,
            );
        } else {
            $faceResult = [
                'processable' => true,
                'verified' => true,
                'message' => 'Face verification is disabled. Continue to the patrol checklist.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => false,
                'liveness_challenge' => null,
            ];
        }

        if (! $faceResult['processable']) {
            return back()
                ->withInput()
                ->with('warning', $faceResult['message']);
        }

        $capturedDescriptor = $faceResult['captured_descriptor'];
        $capturedImage = $faceResult['captured_image'];
        $matchDistance = $faceResult['match_distance'];
        $facialStatus = $faceVerificationEnabled ? ($faceResult['verified'] ? 'verified' : 'failed') : 'not_required';
        $patrolStatus = $facialStatus === 'failed' ? 'suspicious' : 'valid';
        $checkpoint = $patrolLog->checkpoint;
        $checklistProofPhotoFiles = $this->checklistProofPhotoFiles($request);
        $incidentImageFiles = $this->incidentImageFiles($request);
        $checklistProofPhotoError = $this->checklistProofPhotoError($request, $facialStatus, $checklistProofPhotoFiles);
        $incidentImageError = $this->incidentImageError($request, $incidentImageFiles);
        $incidentReport = null;
        $submittedAt = now(config('app.timezone'));

        if ($checklistProofPhotoError) {
            return back()
                ->withInput()
                ->withErrors(['checklist_photos' => $checklistProofPhotoError]);
        }

        if ($incidentImageError) {
            return back()
                ->withInput()
                ->withErrors(['incident_images' => $incidentImageError]);
        }

        DB::transaction(function () use ($request, $data, $guard, $patrolLog, $checkpoint, $facialStatus, $patrolStatus, $capturedDescriptor, $capturedImage, $matchDistance, $checklistProofPhotoFiles, $incidentImageFiles, $submittedAt, $verifiedFaceAttempt, $faceResult, $faceVerificationEnabled, &$incidentReport) {
            $patrolLog->update([
                'facial_status' => $facialStatus,
                'status' => $patrolStatus,
                'notes' => $facialStatus === 'failed' ? 'Facial verification failed after a valid RFID scan.' : null,
            ]);

            $this->expireOtherPendingPatrols($guard, $patrolLog);

            if ($faceVerificationEnabled && ! $verifiedFaceAttempt) {
                $this->recordFaceVerificationAttempt($patrolLog, $guard, [
                    'verified' => $facialStatus === 'verified',
                    'captured_descriptor' => $capturedDescriptor,
                    'captured_image' => $capturedImage,
                    'match_distance' => $matchDistance,
                    'liveness_confirmed' => $faceResult['liveness_confirmed'] ?? false,
                    'liveness_challenge' => $faceResult['liveness_challenge'] ?? null,
                ], $submittedAt);
            }

            if ($facialStatus === 'failed') {
                return;
            }

            $checklistResponse = $patrolLog->checklistResponse()->create([
                ...PatrolChecklist::valuesFromRequest($request),
                'item_statuses' => PatrolChecklist::statusesFromRequest($request),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->storeChecklistProofPhotos($checklistResponse, $checklistProofPhotoFiles);

            if ($request->boolean('has_incident')) {
                $incidentReport = IncidentReport::create([
                    'patrol_log_id' => $patrolLog->id,
                    'guard_id' => $guard->id,
                    'checkpoint_id' => $checkpoint?->id,
                    'title' => $data['incident_category'],
                    'incident_type' => $data['incident_category'],
                    'category' => $data['incident_category'],
                    'priority' => $data['incident_priority'] ?? 'normal',
                    'severity' => $this->severityFromPriority($data['incident_priority'] ?? 'normal'),
                    'location' => $checkpoint?->location,
                    'incident_at' => $submittedAt,
                    'occurred_at' => $submittedAt,
                    'reported_at' => $submittedAt,
                    'description' => $data['incident_description'],
                    'status' => 'submitted',
                ]);

                $imagePaths = $this->storeIncidentImages($incidentReport, $incidentImageFiles);

                if ($imagePaths !== []) {
                    $incidentReport->update(['image_path' => $imagePaths[0]]);
                }
            }
        });

        if ($faceVerificationEnabled) {
            $this->forgetLivenessChallengeFor($patrolLog);
        }

        AuditLogger::record(
            $facialStatus === 'failed' ? 'patrol_marked_suspicious' : 'patrol_completed',
            $facialStatus === 'failed' ? 'Face verification failed after RFID scan.' : 'Checkpoint visit recorded successfully.',
            $patrolLog,
            [
                'guard_id' => $guard->id,
                'employee_no' => $guard->employee_no,
                'checkpoint_id' => $checkpoint?->id,
                'checkpoint_code' => $patrolLog->checkpoint_code,
                'facial_status' => $facialStatus,
                'match_distance' => $matchDistance,
                'incident_report_id' => $incidentReport?->id,
            ]
        );

        if ($incidentReport) {
            AuditLogger::record('incident_submitted', 'Incident report submitted with patrol record.', $incidentReport, [
                'patrol_log_id' => $patrolLog->id,
                'category' => $incidentReport->category,
                'priority' => $incidentReport->priority,
            ]);
        }

        if ($facialStatus === 'failed') {
            return back()->with('warning', 'RFID scan saved, but facial verification failed and was marked suspicious.');
        }

        return redirect()->route('patrol.scan')->with('status', 'Checkpoint visit recorded successfully.');
    }

    private function latestPendingPatrolFor(Guard $guard): ?PatrolLog
    {
        $latestPatrolLog = PatrolLog::with(['securityGuard', 'checkpoint'])
            ->where('guard_id', $guard->id)
            ->latest('scanned_at')
            ->latest('id')
            ->first();

        if (! $latestPatrolLog || ! $this->isPendingPatrol($latestPatrolLog)) {
            return null;
        }

        return $latestPatrolLog;
    }

    private function isPendingPatrol(PatrolLog $patrolLog): bool
    {
        if (! FaceVerification::enabled()) {
            return $patrolLog->rfid_status === 'valid'
                && in_array($patrolLog->facial_status, ['pending', 'not_required'], true)
                && in_array($patrolLog->status, ['pending_face', 'pending_checklist'], true);
        }

        return $patrolLog->rfid_status === 'valid'
            && $patrolLog->facial_status === 'pending'
            && $patrolLog->status === 'pending_face';
    }

    private function expireOtherPendingPatrols(Guard $guard, PatrolLog $completedPatrolLog): void
    {
        PatrolLog::where('guard_id', $guard->id)
            ->whereKeyNot($completedPatrolLog->id)
            ->where('rfid_status', 'valid')
            ->whereIn('facial_status', ['pending', 'not_required'])
            ->whereIn('status', ['pending_face', 'pending_checklist'])
            ->update([
                'facial_status' => 'expired',
                'status' => 'expired',
                'notes' => 'This pending checkpoint scan was replaced by a newer completed patrol scan.',
            ]);
    }

    private function patrolLogPayload(PatrolLog $patrolLog): array
    {
        $faceVerificationEnabled = FaceVerification::enabled();
        $verifiedFaceAttempt = $faceVerificationEnabled
            ? $this->latestVerifiedFaceAttemptFor($patrolLog)
            : null;
        $livenessChallenge = $faceVerificationEnabled
            ? $this->livenessChallengeFor($patrolLog)
            : null;

        return [
            'id' => $patrolLog->id,
            'rfid_uid' => $patrolLog->rfid_uid,
            'checkpoint_code' => $patrolLog->checkpoint_code,
            'status' => $patrolLog->status,
            'facial_status' => $patrolLog->facial_status,
            'face_verified' => ! $faceVerificationEnabled || (bool) $verifiedFaceAttempt,
            'face_verification_enabled' => $faceVerificationEnabled,
            'match_distance' => $verifiedFaceAttempt?->match_distance,
            'face_liveness_challenge' => $livenessChallenge,
            'face_liveness_label' => FaceVerification::livenessLabel($livenessChallenge),
            'scanned_at' => $patrolLog->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            'guard' => [
                'name' => $patrolLog->securityGuard?->name,
                'employee_no' => $patrolLog->securityGuard?->employee_no,
            ],
            'checkpoint' => [
                'name' => $patrolLog->checkpoint?->name ?? $patrolLog->checkpoint_code,
                'code' => $patrolLog->checkpoint?->code ?? $patrolLog->checkpoint_code,
                'location' => $patrolLog->checkpoint?->location,
                'device_uid' => $patrolLog->checkpoint?->device_uid,
            ],
        ];
    }

    private function latestVerifiedFaceAttemptFor(PatrolLog $patrolLog): ?FaceVerificationAttempt
    {
        return $patrolLog->faceVerificationAttempts()
            ->where('status', 'verified')
            ->latest('verified_at')
            ->latest('id')
            ->first();
    }

    private function recordFaceVerificationAttempt(PatrolLog $patrolLog, Guard $guard, array $faceResult, mixed $verifiedAt = null): FaceVerificationAttempt
    {
        $facialStatus = ($faceResult['verified'] ?? false) ? 'verified' : 'failed';
        $capturedImagePath = $this->storeFaceCapture($faceResult['captured_image'] ?? null, $guard);
        $verifiedAt ??= now(config('app.timezone'));

        return $patrolLog->faceVerificationAttempts()->create([
            'guard_id' => $guard->id,
            'status' => $facialStatus,
            'match_distance' => $faceResult['match_distance'] ?? null,
            'match_threshold' => FaceVerification::matchThreshold(),
            'liveness_challenge' => $faceResult['liveness_challenge'] ?? null,
            'liveness_confirmed_at' => ($faceResult['liveness_confirmed'] ?? false) ? $verifiedAt : null,
            'captured_image_path' => $capturedImagePath,
            'captured_descriptor' => $faceResult['captured_descriptor'] ?? null,
            'notes' => match (true) {
                $facialStatus === 'verified' => 'Face matched the guard pre-registered face reference for ESP32 RFID scan.',
                default => 'Face did not match the guard pre-registered face reference after ESP32 RFID scan.',
            },
            'verified_at' => $verifiedAt,
        ]);
    }

    private function evaluateFaceVerification(Guard $guard, ?string $descriptorJson, ?string $captureDataUrl, bool $livenessConfirmed = false, ?string $livenessChallenge = null): array
    {
        if (! $this->hasCompletedFaceRegistration($guard)) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Live face registration is not ready for this guard. Open Profile Settings and complete all five samples first.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => false,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        $storedDescriptors = $this->storedFaceDescriptors($guard);

        if ($storedDescriptors === []) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Live face registration is not ready for this guard. Open Profile Settings and complete registration first.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => false,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        if (! $livenessConfirmed || ! FaceVerification::isLivenessChallenge($livenessChallenge)) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Complete the random liveness challenge before face verification.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => false,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        $capturedImage = $this->imageFromCaptureDataUrl($captureDataUrl);

        if (! $capturedImage) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Capture a clear live face photo before submitting the patrol checklist.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => true,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        $capturedDescriptor = $this->descriptorFromJson($descriptorJson);

        if (! $capturedDescriptor) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Face data is not ready. Capture a clear front-facing face and wait for processing to finish.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
                'liveness_confirmed' => true,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        if ($this->isExactDescriptorReplay($capturedDescriptor, $storedDescriptors)) {
            return [
                'processable' => true,
                'verified' => false,
                'message' => 'Face verification rejected a reused face reference. Capture a new live face photo.',
                'captured_descriptor' => $capturedDescriptor,
                'captured_image' => $capturedImage,
                'match_distance' => 0.0,
                'liveness_confirmed' => true,
                'liveness_challenge' => $livenessChallenge,
            ];
        }

        $matchDistance = $this->bestMatchDistance($capturedDescriptor, $storedDescriptors);
        $verified = $matchDistance !== null && $matchDistance <= FaceVerification::matchThreshold();

        return [
            'processable' => true,
            'verified' => $verified,
            'message' => $verified
                ? 'Face verified successfully. Continue to the patrol checklist.'
                : 'Face mismatch. This face does not match the registered guard face.',
            'captured_descriptor' => $capturedDescriptor,
            'captured_image' => $capturedImage,
            'match_distance' => $matchDistance,
            'liveness_confirmed' => true,
            'liveness_challenge' => $livenessChallenge,
        ];
    }

    private function livenessChallengeFor(PatrolLog $patrolLog): string
    {
        $challengesByPatrol = session(self::FACE_LIVENESS_SESSION_KEY, []);

        if (! is_array($challengesByPatrol)) {
            $challengesByPatrol = [];
        }

        $challenge = $challengesByPatrol[$patrolLog->id] ?? null;

        if (! FaceVerification::isLivenessChallenge($challenge)) {
            $pool = FaceVerification::livenessChallenges();
            $challenge = $pool[array_rand($pool)];
            $challengesByPatrol[$patrolLog->id] = $challenge;
            session([self::FACE_LIVENESS_SESSION_KEY => $challengesByPatrol]);
        }

        return $challenge;
    }

    private function livenessChallengeMatches(PatrolLog $patrolLog, ?string $challenge): bool
    {
        if (! FaceVerification::isLivenessChallenge($challenge)) {
            return false;
        }

        $challengesByPatrol = session(self::FACE_LIVENESS_SESSION_KEY, []);

        return is_array($challengesByPatrol)
            && ($challengesByPatrol[$patrolLog->id] ?? null) === $challenge;
    }

    private function forgetLivenessChallengeFor(PatrolLog $patrolLog): void
    {
        $challengesByPatrol = session(self::FACE_LIVENESS_SESSION_KEY, []);

        if (! is_array($challengesByPatrol)) {
            return;
        }

        unset($challengesByPatrol[$patrolLog->id]);
        session([self::FACE_LIVENESS_SESSION_KEY => $challengesByPatrol]);
    }

    private function storedFaceDescriptors(Guard $guard): array
    {
        return $guard->faceDescriptors()
            ->whereNotNull('descriptor')
            ->get()
            ->pluck('descriptor')
            ->filter(fn ($descriptor) => is_array($descriptor) && count($descriptor) === 128)
            ->values()
            ->all();
    }

    private function hasCompletedFaceRegistration(Guard $guard): bool
    {
        return FaceVerification::hasCompleteRegistration($guard->faceDescriptors()->get(['descriptor', 'capture_type']));
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

    private function isExactDescriptorReplay(array $capturedDescriptor, array $storedDescriptors): bool
    {
        foreach ($storedDescriptors as $storedDescriptor) {
            if (count($capturedDescriptor) !== count($storedDescriptor)) {
                continue;
            }

            $differences = collect($capturedDescriptor)
                ->filter(fn ($value, $index) => abs((float) $value - (float) $storedDescriptor[$index]) > 0.00000001);

            if ($differences->isEmpty()) {
                return true;
            }
        }

        return false;
    }

    private function storeFaceCapture(?array $image, Guard $guard): ?string
    {
        if (! $image) {
            return null;
        }

        $path = 'face-verifications/'.$guard->id.'/'.Str::uuid().'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        return $path;
    }

    private function checklistProofPhotoError(Request $request, string $facialStatus, array $checklistProofPhotoFiles): ?string
    {
        if (! in_array($facialStatus, ['verified', 'not_required'], true)) {
            return null;
        }

        if ($checklistProofPhotoFiles === []) {
            return 'Take at least one checkpoint proof photo before submitting the patrol record.';
        }

        $photoFields = collect($checklistProofPhotoFiles)->pluck('field');
        $missingIssuePhotoLabels = PatrolChecklist::issueFieldsFromRequest($request)
            ->reject(fn (string $field) => $photoFields->contains($field))
            ->map(fn (string $field) => PatrolChecklist::label($field))
            ->filter()
            ->values();

        if ($missingIssuePhotoLabels->isNotEmpty()) {
            return 'Take a proof photo for each checklist item marked Issue Found: '.$missingIssuePhotoLabels->implode(', ').'.';
        }

        return null;
    }

    private function checklistProofPhotoFiles(Request $request): array
    {
        $files = $request->file('checklist_photos', []);

        if (! is_array($files)) {
            return [];
        }

        return collect(PatrolChecklist::fields())
            ->filter(fn (string $field) => ($files[$field] ?? null) instanceof UploadedFile && $files[$field]->isValid())
            ->map(fn (string $field) => [
                'field' => $field,
                'file' => $files[$field],
            ])
            ->values()
            ->all();
    }

    private function storeChecklistProofPhotos(ChecklistResponse $checklistResponse, array $checklistProofPhotoFiles): void
    {
        foreach ($checklistProofPhotoFiles as $index => $item) {
            $file = $item['file'];
            $path = $file->store('checklist-proof-photos', 'public');
            $contents = file_get_contents($file->getRealPath());
            $field = $item['field'];

            $checklistResponse->proofPhotos()->create([
                'patrol_log_id' => $checklistResponse->patrol_log_id,
                'item_key' => $field,
                'item_label' => PatrolChecklist::label($field) ?? Str::of($field)->replace('_', ' ')->title()->toString(),
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                'image_data' => $contents === false ? null : base64_encode($contents),
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function incidentImageError(Request $request, array $incidentImageFiles): ?string
    {
        if (! $request->boolean('has_incident')) {
            return null;
        }

        $uploadCount = $this->uploadedFileCount($request->file('incident_images', []));
        $cameraCount = $this->uploadedFileCount($request->file('incident_camera_images', []))
            + $this->uploadedFileCount($request->file('incident_image'));

        if (count($incidentImageFiles) > 3) {
            return 'Attach up to 3 incident images only.';
        }

        if (count($incidentImageFiles) === 0) {
            return 'Attach at least one incident image before submitting the incident report.';
        }

        if ($uploadCount === 1 && $cameraCount === 0) {
            return 'Upload at least 2 images, or use Take Photo for a single camera image.';
        }

        return null;
    }

    private function incidentImageFiles(Request $request): array
    {
        return collect([
            ...$this->uploadedFilesWithSource($request->file('incident_images', []), 'upload'),
            ...$this->uploadedFilesWithSource($request->file('incident_camera_images', []), 'camera'),
            ...$this->uploadedFilesWithSource($request->file('incident_image'), 'camera'),
        ])
            ->filter(fn ($item) => $item['file'] instanceof UploadedFile && $item['file']->isValid())
            ->values()
            ->all();
    }

    private function uploadedFilesWithSource(mixed $files, string $source): array
    {
        if ($files instanceof UploadedFile) {
            return [['file' => $files, 'source' => $source]];
        }

        if (! is_array($files)) {
            return [];
        }

        return collect($files)
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => ['file' => $file, 'source' => $source])
            ->values()
            ->all();
    }

    private function uploadedFileCount(mixed $files): int
    {
        return count($this->uploadedFilesWithSource($files, 'upload'));
    }

    private function storeIncidentImages(IncidentReport $incidentReport, array $incidentImageFiles): array
    {
        $paths = [];

        foreach (array_slice($incidentImageFiles, 0, 3) as $index => $item) {
            $file = $item['file'];
            $path = $file->store('incident-reports', 'public');
            $contents = file_get_contents($file->getRealPath());
            $paths[] = $path;

            $incidentReport->images()->create([
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                'image_data' => $contents === false ? null : base64_encode($contents),
                'source' => $item['source'],
                'sort_order' => $index + 1,
            ]);
        }

        return $paths;
    }

    private function unknownGuard(): Guard
    {
        return Guard::firstOrCreate(
            ['employee_no' => 'UNKNOWN'],
            [
                'name' => 'Unregistered RFID Card',
                'rfid_uid' => 'UNKNOWN',
                'status' => 'inactive',
            ]
        );
    }

    private function severityFromPriority(string $priority): string
    {
        return match ($priority) {
            'critical' => 'critical',
            'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
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

    private function bestMatchDistance(array $capturedDescriptor, array $storedDescriptors): ?float
    {
        $bestDistance = null;

        foreach ($storedDescriptors as $storedDescriptor) {
            $distance = $this->faceDistance($capturedDescriptor, $storedDescriptor);

            if ($distance === null) {
                continue;
            }

            $bestDistance = $bestDistance === null ? $distance : min($bestDistance, $distance);
        }

        return $bestDistance === null ? null : round($bestDistance, 6);
    }

    private function faceDistance(array $firstDescriptor, array $secondDescriptor): ?float
    {
        if (count($firstDescriptor) !== count($secondDescriptor)) {
            return null;
        }

        $sum = 0;

        foreach ($firstDescriptor as $index => $value) {
            $difference = (float) $value - (float) $secondDescriptor[$index];
            $sum += $difference * $difference;
        }

        return sqrt($sum);
    }
}

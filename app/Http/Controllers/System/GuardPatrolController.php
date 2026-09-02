<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
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
    public function create(): View
    {
        $guardProfile = auth()->user()?->guardProfile;
        $patrolScheduleOpen = PatrolSchedule::isOpen();
        $faceRegistrationComplete = $guardProfile
            ? $this->hasCompletedFaceRegistration($guardProfile)
            : false;

        return view('system.patrols.scan', [
            'checkpoints' => Checkpoint::where('status', 'active')->orderBy('name')->get(),
            'guardProfile' => $guardProfile,
            'pendingPatrol' => $guardProfile && $patrolScheduleOpen ? $this->latestPendingPatrolFor($guardProfile) : null,
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
        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'face_capture' => ['required', 'string'],
            'captured_descriptor' => ['required', 'string'],
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

        $faceResult = $this->evaluateFaceVerification(
            $guard,
            $data['captured_descriptor'],
            $data['face_capture'],
        );

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
        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'face_capture' => ['nullable', 'string'],
            'captured_descriptor' => ['nullable', 'string'],
            ...PatrolChecklist::validationRules(),
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
            ->where('facial_status', 'pending')
            ->where('status', 'pending_face')
            ->first();

        if (! $patrolLog) {
            return back()->with('warning', 'No pending RFID scan is available for this guard. Please scan your card at the checkpoint again.');
        }

        $faceResult = $this->evaluateFaceVerification(
            $guard,
            $data['captured_descriptor'] ?? null,
            $data['face_capture'] ?? null,
        );

        if (! $faceResult['processable']) {
            return back()
                ->withInput()
                ->with('warning', $faceResult['message']);
        }

        $capturedDescriptor = $faceResult['captured_descriptor'];
        $capturedImage = $faceResult['captured_image'];
        $matchDistance = $faceResult['match_distance'];
        $facialStatus = $faceResult['verified'] ? 'verified' : 'failed';
        $patrolStatus = $facialStatus === 'verified' ? 'valid' : 'suspicious';
        $checkpoint = $patrolLog->checkpoint;
        $incidentImageFiles = $this->incidentImageFiles($request);
        $incidentImageError = $this->incidentImageError($request, $incidentImageFiles);
        $incidentReport = null;
        $submittedAt = now(config('app.timezone'));

        if ($incidentImageError) {
            return back()
                ->withInput()
                ->withErrors(['incident_images' => $incidentImageError]);
        }

        DB::transaction(function () use ($request, $data, $guard, $patrolLog, $checkpoint, $facialStatus, $patrolStatus, $capturedDescriptor, $capturedImage, $matchDistance, $incidentImageFiles, $submittedAt, &$incidentReport) {
            $capturedImagePath = $this->storeFaceCapture($capturedImage, $guard);

            $patrolLog->update([
                'facial_status' => $facialStatus,
                'status' => $patrolStatus,
                'notes' => $facialStatus === 'failed' ? 'Facial verification failed after a valid RFID scan.' : null,
            ]);

            $this->expireOtherPendingPatrols($guard, $patrolLog);

            $patrolLog->faceVerificationAttempts()->create([
                'guard_id' => $guard->id,
                'status' => $facialStatus,
                'match_distance' => $matchDistance,
                'match_threshold' => FaceVerification::matchThreshold(),
                'captured_image_path' => $capturedImagePath,
                'captured_descriptor' => $capturedDescriptor,
                'notes' => match (true) {
                    $facialStatus === 'verified' => 'Face matched the guard pre-registered face reference for ESP32 RFID scan.',
                    default => 'Face did not match the guard pre-registered face reference after ESP32 RFID scan.',
                },
                'verified_at' => $submittedAt,
            ]);

            if ($facialStatus === 'failed') {
                return;
            }

            $patrolLog->checklistResponse()->create([
                ...PatrolChecklist::valuesFromRequest($request),
                'remarks' => $data['remarks'] ?? null,
            ]);

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

        AuditLogger::record(
            $facialStatus === 'verified' ? 'patrol_completed' : 'patrol_marked_suspicious',
            $facialStatus === 'verified' ? 'Checkpoint visit recorded successfully.' : 'Face verification failed after RFID scan.',
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

        if (! $latestPatrolLog || ! $this->isPendingFacePatrol($latestPatrolLog)) {
            return null;
        }

        return $latestPatrolLog;
    }

    private function isPendingFacePatrol(PatrolLog $patrolLog): bool
    {
        return $patrolLog->rfid_status === 'valid'
            && $patrolLog->facial_status === 'pending'
            && $patrolLog->status === 'pending_face';
    }

    private function expireOtherPendingPatrols(Guard $guard, PatrolLog $completedPatrolLog): void
    {
        PatrolLog::where('guard_id', $guard->id)
            ->whereKeyNot($completedPatrolLog->id)
            ->where('rfid_status', 'valid')
            ->where('facial_status', 'pending')
            ->where('status', 'pending_face')
            ->update([
                'facial_status' => 'expired',
                'status' => 'expired',
                'notes' => 'This pending face verification was replaced by a newer completed patrol scan.',
            ]);
    }

    private function patrolLogPayload(PatrolLog $patrolLog): array
    {
        return [
            'id' => $patrolLog->id,
            'rfid_uid' => $patrolLog->rfid_uid,
            'checkpoint_code' => $patrolLog->checkpoint_code,
            'status' => $patrolLog->status,
            'facial_status' => $patrolLog->facial_status,
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

    private function evaluateFaceVerification(Guard $guard, ?string $descriptorJson, ?string $captureDataUrl): array
    {
        $storedDescriptors = $this->storedFaceDescriptors($guard);

        if ($storedDescriptors === []) {
            return [
                'processable' => false,
                'verified' => false,
                'message' => 'Live face registration is not ready for this guard. Open Profile Settings and complete registration first.',
                'captured_descriptor' => null,
                'captured_image' => null,
                'match_distance' => null,
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
        ];
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
        return $this->storedFaceDescriptors($guard) !== [];
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
            $paths[] = $path;

            $incidentReport->images()->create([
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
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

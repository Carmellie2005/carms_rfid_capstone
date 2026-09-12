<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ChecklistResponse;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use App\Support\ImageCompressor;
use App\Support\PatrolChecklist;
use App\Support\PatrolSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        $pendingPatrol = $guardProfile && $patrolScheduleOpen
            ? $this->latestPendingPatrolFor($guardProfile)
            : null;

        return view('system.patrols.scan', [
            'checkpoints' => Checkpoint::where('status', 'active')->orderBy('name')->get(),
            'guardProfile' => $guardProfile,
            'pendingPatrol' => $pendingPatrol,
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'area_selfie_capture' => ['required', 'string'],
            'area_selfie_captured_at' => ['required', 'date'],
            'area_selfie_latitude' => ['required', 'numeric', 'between:-90,90'],
            'area_selfie_longitude' => ['required', 'numeric', 'between:-180,180'],
            'area_selfie_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            ...PatrolChecklist::validationRules(),
            'checklist_photos' => ['nullable', 'array', 'max:'.count(PatrolChecklist::fields())],
            'checklist_photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'has_incident' => ['nullable', 'boolean'],
            'incident_category' => ['nullable', 'required_if:has_incident,1', 'string', 'max:100', Rule::in(PatrolChecklist::incidentCategories())],
            'incident_priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'incident_description' => ['nullable', 'required_if:has_incident,1', 'string', 'max:3000'],
            'incident_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'incident_images' => ['nullable', 'array', 'max:3'],
            'incident_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'incident_camera_images' => ['nullable', 'array', 'max:3'],
            'incident_camera_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
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
            ->whereIn('facial_status', ['pending', 'not_required'])
            ->whereIn('status', ['pending_face', 'pending_selfie', 'pending_checklist'])
            ->first();

        if (! $patrolLog) {
            return back()->with('warning', 'No pending RFID scan is available for this guard. Please scan your card at the checkpoint again.');
        }

        $areaSelfieImage = $this->imageFromCaptureDataUrl($data['area_selfie_capture'] ?? null);

        if (! $areaSelfieImage) {
            return back()
                ->withInput()
                ->withErrors(['area_selfie_capture' => 'Take a clear area selfie before submitting the patrol record.']);
        }

        $facialStatus = 'not_required';
        $patrolStatus = 'valid';
        $checkpoint = $patrolLog->checkpoint;
        $checklistProofPhotoFiles = $this->checklistProofPhotoFiles($request);
        $incidentImageFiles = $this->incidentImageFiles($request);
        $checklistProofPhotoError = $this->checklistProofPhotoError($request, $checklistProofPhotoFiles);
        $incidentImageError = $this->incidentImageError($request, $incidentImageFiles);
        $incidentReport = null;
        $submittedAt = now(config('app.timezone'));
        $selfieCapturedAt = Carbon::parse($data['area_selfie_captured_at'])->timezone(config('app.timezone'));

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

        DB::transaction(function () use ($request, $data, $guard, $patrolLog, $checkpoint, $facialStatus, $patrolStatus, $areaSelfieImage, $selfieCapturedAt, $checklistProofPhotoFiles, $incidentImageFiles, $submittedAt, &$incidentReport) {
            $areaSelfiePath = $this->storePatrolAreaSelfie($areaSelfieImage, $guard);

            $patrolLog->update([
                'facial_status' => $facialStatus,
                'status' => $patrolStatus,
                'area_selfie_path' => $areaSelfiePath,
                'area_selfie_mime_type' => $areaSelfieImage['mime_type'],
                'area_selfie_image_data' => base64_encode($areaSelfieImage['contents']),
                'area_selfie_captured_at' => $selfieCapturedAt,
                'area_selfie_latitude' => $data['area_selfie_latitude'],
                'area_selfie_longitude' => $data['area_selfie_longitude'],
                'area_selfie_accuracy' => $data['area_selfie_accuracy'] ?? null,
                'notes' => null,
            ]);

            $this->expireOtherPendingPatrols($guard, $patrolLog);

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

        AuditLogger::record(
            'patrol_completed',
            'Checkpoint visit recorded successfully with area selfie.',
            $patrolLog,
            [
                'guard_id' => $guard->id,
                'employee_no' => $guard->employee_no,
                'checkpoint_id' => $checkpoint?->id,
                'checkpoint_code' => $patrolLog->checkpoint_code,
                'facial_status' => $facialStatus,
                'area_selfie_captured_at' => $selfieCapturedAt->toDateTimeString(),
                'area_selfie_latitude' => $data['area_selfie_latitude'],
                'area_selfie_longitude' => $data['area_selfie_longitude'],
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
        return $patrolLog->rfid_status === 'valid'
            && in_array($patrolLog->facial_status, ['pending', 'not_required'], true)
            && in_array($patrolLog->status, ['pending_face', 'pending_selfie', 'pending_checklist'], true);
    }

    private function expireOtherPendingPatrols(Guard $guard, PatrolLog $completedPatrolLog): void
    {
        PatrolLog::where('guard_id', $guard->id)
            ->whereKeyNot($completedPatrolLog->id)
            ->where('rfid_status', 'valid')
            ->whereIn('facial_status', ['pending', 'not_required'])
            ->whereIn('status', ['pending_face', 'pending_selfie', 'pending_checklist'])
            ->update([
                'facial_status' => 'expired',
                'status' => 'expired',
                'notes' => 'This pending checkpoint scan was replaced by a newer completed patrol scan.',
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
            'area_selfie_captured' => filled($patrolLog->area_selfie_path) || filled($patrolLog->area_selfie_image_data),
            'area_selfie_captured_at' => $patrolLog->area_selfie_captured_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            'area_selfie_latitude' => $patrolLog->area_selfie_latitude,
            'area_selfie_longitude' => $patrolLog->area_selfie_longitude,
            'area_selfie_accuracy' => $patrolLog->area_selfie_accuracy,
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

        return ImageCompressor::compressedJpeg($contents);
    }

    private function storePatrolAreaSelfie(array $image, Guard $guard): string
    {
        $path = 'patrol-area-selfies/'.$guard->id.'/'.Str::uuid().'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        return $path;
    }

    private function checklistProofPhotoError(Request $request, array $checklistProofPhotoFiles): ?string
    {
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
            $image = $this->compressedUploadedImage($file);
            $path = 'checklist-proof-photos/'.Str::uuid().'.'.$image['extension'];
            Storage::disk('public')->put($path, $image['contents']);
            $field = $item['field'];

            $checklistResponse->proofPhotos()->create([
                'patrol_log_id' => $checklistResponse->patrol_log_id,
                'item_key' => $field,
                'item_label' => PatrolChecklist::label($field) ?? Str::of($field)->replace('_', ' ')->title()->toString(),
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $image['mime_type'],
                'image_data' => base64_encode($image['contents']),
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
            $image = $this->compressedUploadedImage($file);
            $path = 'incident-reports/'.Str::uuid().'.'.$image['extension'];
            Storage::disk('public')->put($path, $image['contents']);
            $paths[] = $path;

            $incidentReport->images()->create([
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $image['mime_type'],
                'image_data' => base64_encode($image['contents']),
                'source' => $item['source'],
                'sort_order' => $index + 1,
            ]);
        }

        return $paths;
    }

    private function compressedUploadedImage(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            return [
                'extension' => $file->extension() ?: 'jpg',
                'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                'contents' => '',
            ];
        }

        return ImageCompressor::compressedJpeg($contents, sourcePath: $file->getRealPath()) ?? [
            'extension' => $file->extension() ?: 'jpg',
            'mime_type' => $file->getMimeType() ?: 'image/jpeg',
            'contents' => $contents,
        ];
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

}

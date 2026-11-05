<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentEvidence;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use App\Support\ImageCompressor;
use App\Support\PatrolChecklist;
use App\Services\SupervisorEmailNotifier;
use App\Services\SupervisorPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuardPatrolController extends Controller
{
    public function create(): View
    {
        $user = auth()->user();
        $guardProfile = $user?->guardProfile;
        $mustChangePassword = (bool) $user?->must_change_password;
        $pendingPatrol = $guardProfile && ! $mustChangePassword
            ? $this->latestPendingPatrolFor($guardProfile)
            : null;

        return view('system.patrols.scan', [
            'checkpoints' => Checkpoint::where('status', 'active')->orderBy('name')->get(),
            'guardProfile' => $guardProfile,
            'mustChangePassword' => $mustChangePassword,
            'pendingPatrol' => $pendingPatrol,
        ]);
    }

    public function incidentEvidence(Request $request): JsonResponse
    {
        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return response()->json([
                'message' => 'Signed-in account is not linked to a guard profile.',
                'evidence' => [],
            ], 403);
        }

        $evidence = IncidentEvidence::query()
            ->where('guard_id', $guard->id)
            ->whereNull('incident_report_id')
            ->latest('captured_at')
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'evidence' => $evidence->map(fn (IncidentEvidence $item) => $this->incidentEvidencePayload($item))->values(),
        ]);
    }

    public function storeIncidentEvidence(Request $request): JsonResponse
    {
        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return response()->json([
                'message' => 'Signed-in account is not linked to a guard profile.',
            ], 403);
        }

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'captured_at' => ['required', 'date'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $image = $this->compressedUploadedImage($data['image']);

        if ($image['contents'] === '') {
            return response()->json([
                'message' => 'The incident evidence image could not be saved. Please try again.',
            ], 422);
        }

        $path = 'incident-evidence/'.$guard->id.'/'.Str::uuid().'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        $evidence = IncidentEvidence::create([
            'guard_id' => $guard->id,
            'image_path' => $path,
            'original_name' => $data['image']->getClientOriginalName(),
            'mime_type' => $image['mime_type'],
            'image_data' => base64_encode($image['contents']),
            'captured_at' => Carbon::parse($data['captured_at'])->timezone(config('app.timezone')),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
        ]);

        return response()->json([
            'message' => 'Incident evidence saved with its capture time and GPS.',
            'evidence' => $this->incidentEvidencePayload($evidence),
        ], 201);
    }

    public function incidentEvidenceImage(Request $request, IncidentEvidence $incidentEvidence): Response
    {
        $guardId = $request->user()?->guardProfile?->id;

        abort_unless($guardId && $incidentEvidence->guard_id === $guardId, 403);

        $contents = null;
        $mimeType = $incidentEvidence->mime_type ?: 'image/jpeg';

        if ($incidentEvidence->image_path && Storage::disk('public')->exists($incidentEvidence->image_path)) {
            $contents = Storage::disk('public')->get($incidentEvidence->image_path);
            $mimeType = Storage::disk('public')->mimeType($incidentEvidence->image_path) ?: $mimeType;
        } elseif ($incidentEvidence->image_data) {
            $decoded = base64_decode($incidentEvidence->image_data, true);
            $contents = $decoded === false ? null : $decoded;
        }

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function destroyIncidentEvidence(Request $request, IncidentEvidence $incidentEvidence): JsonResponse
    {
        $guardId = $request->user()?->guardProfile?->id;

        abort_unless($guardId && $incidentEvidence->guard_id === $guardId, 403);

        if ($incidentEvidence->incident_report_id) {
            return response()->json([
                'message' => 'Evidence already attached to an incident report and cannot be removed here.',
            ], 422);
        }

        if ($incidentEvidence->image_path) {
            Storage::disk('public')->delete($incidentEvidence->image_path);
        }

        $incidentEvidence->delete();

        return response()->json(['message' => 'Incident evidence removed.']);
    }

    public function pendingScan(Request $request): JsonResponse
    {
        $user = $request->user();
        $guard = $user?->guardProfile;

        if (! $guard) {
            return response()->json([
                'pending' => false,
                'message' => 'Signed-in account is not linked to a guard profile.',
            ], 403);
        }

        if ($user?->must_change_password) {
            return response()->json([
                'pending' => false,
                'message' => 'Change your temporary password before scanning a checkpoint.',
                'patrol_log' => null,
            ], 403);
        }

        $patrolLog = $this->latestPendingPatrolFor($guard);

        return response()->json([
            'pending' => (bool) $patrolLog,
            'patrol_log' => $patrolLog ? $this->patrolLogPayload($patrolLog) : null,
        ]);
    }

    public function store(Request $request, SupervisorEmailNotifier $emailNotifier, SupervisorPushNotifier $pushNotifier): RedirectResponse
    {
        $user = $request->user();
        $guard = $user?->guardProfile;

        if (! $guard) {
            return back()->with('warning', 'Signed-in account is not linked to an active guard profile.');
        }

        if ($user?->must_change_password) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Change your temporary password before scanning a checkpoint.');
        }

        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer', 'exists:patrol_logs,id'],
            'area_selfie_capture' => ['required', 'string'],
            'area_selfie_captured_at' => ['required', 'date'],
            'area_selfie_latitude' => ['required', 'numeric', 'between:-90,90'],
            'area_selfie_longitude' => ['required', 'numeric', 'between:-180,180'],
            'area_selfie_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            ...PatrolChecklist::validationRules(),
            'has_incident' => ['nullable', 'boolean'],
            'incident_category' => ['nullable', 'required_if:has_incident,1', 'string', 'max:100', Rule::in(PatrolChecklist::incidentCategories())],
            'incident_priority' => ['nullable', 'required_if:has_incident,1', Rule::in(array_keys(PatrolChecklist::incidentPriorityOptions()))],
            'incident_description' => ['nullable', 'required_if:has_incident,1', 'string', 'max:3000'],
            'incident_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'incident_camera_images' => ['nullable', 'array', 'max:3'],
            'incident_camera_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'incident_evidence_ids' => ['nullable', 'array', 'max:3'],
            'incident_evidence_ids.*' => ['integer'],
        ], [
            ...PatrolChecklist::validationMessages(),
            'patrol_log_id.required' => 'Scan your RFID card before submitting the patrol record.',
            'patrol_log_id.exists' => 'This pending RFID scan is no longer available. Please scan your card again.',
            'area_selfie_capture.required' => 'Please take the required area selfie before submitting.',
            'area_selfie_captured_at.required' => 'Please retake the area selfie so the capture time can be saved.',
            'area_selfie_latitude.required' => 'Location is required before submitting. Please enable GPS/location services and take the photo again.',
            'area_selfie_longitude.required' => 'Location is required before submitting. Please enable GPS/location services and take the photo again.',
            'area_selfie_latitude.numeric' => 'The GPS location could not be read. Please take the photo again.',
            'area_selfie_longitude.numeric' => 'The GPS location could not be read. Please take the photo again.',
            'incident_category.required_if' => 'Please choose an incident category.',
            'incident_priority.required_if' => 'Please choose the incident priority.',
            'incident_description.required_if' => 'Please describe what happened in the incident report.',
            'incident_camera_images.array' => 'Please attach at least two incident photos before submitting the incident report.',
            'incident_camera_images.max' => 'Please take up to 3 incident photos only.',
            'incident_camera_images.*.image' => 'Please take a valid incident photo.',
            'incident_camera_images.*.mimes' => 'Incident photos must be JPG, PNG, or WEBP.',
            'incident_camera_images.*.max' => 'Each incident photo must be 12 MB or smaller.',
            'incident_evidence_ids.max' => 'Please attach up to 3 saved incident evidence images only.',
        ]);

        $patrolLog = PatrolLog::with('checkpoint')
            ->whereKey($data['patrol_log_id'])
            ->where('guard_id', $guard->id)
            ->where('rfid_status', 'valid')
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

        $patrolStatus = 'valid';
        $checkpoint = $patrolLog->checkpoint;
        $incidentImageFiles = $this->incidentImageFiles($request);
        $incidentEvidenceIds = collect($data['incident_evidence_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $incidentEvidence = $this->incidentEvidenceForSubmission($guard, $incidentEvidenceIds);
        $incidentImageError = $this->incidentImageError($request, $incidentImageFiles, $incidentEvidence->count());
        $incidentReport = null;
        $submittedAt = now(config('app.timezone'));
        $selfieCapturedAt = Carbon::parse($data['area_selfie_captured_at'])->timezone(config('app.timezone'));

        if ($incidentImageError) {
            return back()
                ->withInput()
                ->withErrors(['incident_camera_images' => $incidentImageError]);
        }

        if ($request->boolean('has_incident') && $incidentEvidenceIds->count() !== $incidentEvidence->count()) {
            return back()
                ->withInput()
                ->withErrors(['incident_evidence_ids' => 'One or more saved incident evidence images are no longer available. Please refresh the evidence list and select them again.']);
        }

        DB::transaction(function () use ($request, $data, $guard, $patrolLog, $checkpoint, $patrolStatus, $areaSelfieImage, $selfieCapturedAt, $incidentImageFiles, $incidentEvidence, $submittedAt, &$incidentReport) {
            $areaSelfiePath = $this->storePatrolAreaSelfie($areaSelfieImage, $guard);

            $patrolLog->update([
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
            ]);

            if ($request->boolean('has_incident')) {
                $incidentPriority = $data['incident_priority'] ?? PatrolChecklist::defaultIncidentPriority($data['incident_category'] ?? null);

                $incidentReport = IncidentReport::create([
                    'patrol_log_id' => $patrolLog->id,
                    'guard_id' => $guard->id,
                    'checkpoint_id' => $checkpoint?->id,
                    'title' => $data['incident_category'],
                    'incident_type' => $data['incident_category'],
                    'category' => $data['incident_category'],
                    'priority' => $incidentPriority,
                    'severity' => $this->severityFromPriority($incidentPriority),
                    'location' => $checkpoint?->location,
                    'incident_at' => $submittedAt,
                    'occurred_at' => $submittedAt,
                    'reported_at' => $submittedAt,
                    'description' => $data['incident_description'],
                    'status' => 'submitted',
                ]);

                $imagePaths = $this->storeIncidentImages($incidentReport, $incidentImageFiles);
                $evidenceImagePaths = $this->attachStoredIncidentEvidence($incidentReport, $incidentEvidence, count($imagePaths));
                $imagePaths = [...$imagePaths, ...$evidenceImagePaths];

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

            $emailNotifier->sendIncidentSubmitted($incidentReport);
            $pushNotifier->sendIncidentSubmitted($incidentReport);
        }

        return redirect()->route('patrol.scan')->with('status', 'Checkpoint visit recorded successfully.');
    }

    public function cancel(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'patrol_log_id' => ['required', 'integer'],
        ]);

        $guard = $request->user()?->guardProfile;

        if (! $guard) {
            return $this->cancelResponse($request, 'Signed-in account is not linked to an active guard profile.', 403);
        }

        $patrolLog = PatrolLog::with('checkpoint')
            ->whereKey($data['patrol_log_id'])
            ->where('guard_id', $guard->id)
            ->where('rfid_status', 'valid')
            ->whereIn('status', ['pending_face', 'pending_selfie', 'pending_checklist'])
            ->first();

        if (! $patrolLog) {
            return $this->cancelResponse($request, 'No pending scan is available to cancel.', 404);
        }

        $checkpoint = $patrolLog->checkpoint;

        AuditLogger::record(
            'patrol_scan_cancelled',
            'Pending RFID checkpoint scan cancelled by guard before patrol submission.',
            $guard,
            [
                'guard_id' => $guard->id,
                'employee_no' => $guard->employee_no,
                'patrol_log_id' => $patrolLog->id,
                'checkpoint_id' => $checkpoint?->id,
                'checkpoint_code' => $patrolLog->checkpoint_code,
                'result' => 'cancelled',
            ]
        );

        $this->deletePendingPatrolArtifacts($patrolLog);
        $patrolLog->delete();

        return $this->cancelResponse($request, 'Pending scan cancelled. Scan your RFID again when ready.');
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
            && in_array($patrolLog->status, ['pending_face', 'pending_selfie', 'pending_checklist'], true);
    }

    private function expireOtherPendingPatrols(Guard $guard, PatrolLog $completedPatrolLog): void
    {
        PatrolLog::where('guard_id', $guard->id)
            ->whereKeyNot($completedPatrolLog->id)
            ->where('rfid_status', 'valid')
            ->whereIn('status', ['pending_face', 'pending_selfie', 'pending_checklist'])
            ->update([
                'status' => 'expired',
                'notes' => 'This pending checkpoint scan was replaced by a newer completed patrol scan.',
            ]);
    }

    private function patrolLogPayload(PatrolLog $patrolLog): array
    {
        return [
            'id' => $patrolLog->id,
            'checkpoint_code' => $patrolLog->checkpoint_code,
            'status' => $patrolLog->status,
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

    private function incidentEvidencePayload(IncidentEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'original_name' => $evidence->original_name ?: 'Incident evidence',
            'image_url' => route('patrol.incident-evidence.image', $evidence, false),
            'captured_at' => $evidence->captured_at?->toIso8601String(),
            'captured_at_label' => $evidence->captured_at?->timezone(config('app.timezone'))->format('M d, Y h:i A'),
            'latitude' => (float) $evidence->latitude,
            'longitude' => (float) $evidence->longitude,
            'accuracy' => $evidence->accuracy !== null ? (float) $evidence->accuracy : null,
        ];
    }

    private function incidentEvidenceForSubmission(Guard $guard, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return IncidentEvidence::query()
            ->where('guard_id', $guard->id)
            ->whereNull('incident_report_id')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (IncidentEvidence $evidence) => $ids->search($evidence->id))
            ->values();
    }

    private function attachStoredIncidentEvidence(IncidentReport $incidentReport, Collection $evidenceItems, int $existingCount = 0): array
    {
        $paths = [];

        foreach ($evidenceItems->values() as $index => $evidence) {
            $contents = null;

            if ($evidence->image_path && Storage::disk('public')->exists($evidence->image_path)) {
                $contents = Storage::disk('public')->get($evidence->image_path);
            } elseif ($evidence->image_data) {
                $decoded = base64_decode($evidence->image_data, true);
                $contents = $decoded === false ? null : $decoded;
            }

            if (! is_string($contents) || $contents === '') {
                continue;
            }

            $path = 'incident-reports/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($path, $contents);
            $paths[] = $path;

            $incidentReport->images()->create([
                'incident_evidence_id' => $evidence->id,
                'image_path' => $path,
                'original_name' => $evidence->original_name,
                'mime_type' => $evidence->mime_type ?: 'image/jpeg',
                'image_data' => base64_encode($contents),
                'source' => 'evidence_storage',
                'sort_order' => $existingCount + $index + 1,
            ]);

            $evidence->update(['incident_report_id' => $incidentReport->id]);
        }

        return $paths;
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

    private function deletePendingPatrolArtifacts(PatrolLog $patrolLog): void
    {
        if ($patrolLog->area_selfie_path) {
            Storage::disk('public')->delete($patrolLog->area_selfie_path);
        }

    }

    private function cancelResponse(Request $request, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        $flashKey = $status >= 400 ? 'warning' : 'status';

        return redirect()->route('patrol.scan')->with($flashKey, $message);
    }

    private function incidentImageError(Request $request, array $incidentImageFiles, int $savedEvidenceCount = 0): ?string
    {
        if (! $request->boolean('has_incident')) {
            return null;
        }

        $totalImages = count($incidentImageFiles) + $savedEvidenceCount;

        if ($totalImages > 3) {
            return 'Please take up to 3 incident photos only.';
        }

        if ($totalImages < 2) {
            return 'Please attach at least 2 incident photos before submitting the incident report.';
        }

        return null;
    }

    private function incidentImageFiles(Request $request): array
    {
        return collect([
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

    private function severityFromPriority(string $priority): string
    {
        return match ($priority) {
            'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
    }

}

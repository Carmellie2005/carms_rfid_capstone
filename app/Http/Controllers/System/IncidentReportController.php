<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\IncidentReportImage;
use App\Support\AuditLogger;
use App\Support\ImageCompressor;
use App\Support\PatrolChecklist;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentReportController extends Controller
{
    public function index(Request $request): View
    {
        $incidents = IncidentReport::with(['securityGuard', 'checkpoint', 'patrolLog', 'images'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->latest('incident_at')
            ->paginate(5)
            ->withQueryString();

        return view('system.incidents.index', compact('incidents'));
    }

    public function update(Request $request, IncidentReport $incidentReport): RedirectResponse
    {
        $before = $incidentReport->only(['status', 'admin_notes']);

        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'under_review', 'resolved'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $incidentReport->update($data);

        AuditLogger::record('incident_updated', 'Incident report review status updated.', $incidentReport, [
            'before' => $before,
            'after' => $incidentReport->only(['status', 'admin_notes']),
        ]);

        return redirect()->route('incidents.index')->with('status', 'Incident report updated.');
    }

    public function editForGuard(Request $request, IncidentReport $incidentReport): View
    {
        $this->ensureGuardCanEditIncident($request, $incidentReport);

        $incidentReport->load(['checkpoint', 'patrolLog', 'images']);

        return view('system.incidents.guard-edit', [
            'incident' => $incidentReport,
            'incidentCategories' => PatrolChecklist::incidentCategories(),
            'priorityOptions' => [
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'critical' => 'Critical',
            ],
        ]);
    }

    public function updateForGuard(Request $request, IncidentReport $incidentReport): RedirectResponse
    {
        $this->ensureGuardCanEditIncident($request, $incidentReport);

        $incidentReport->load('images');

        $data = $request->validate([
            'category' => ['required', 'string', 'max:100', Rule::in(PatrolChecklist::incidentCategories())],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:3000'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
            'incident_images' => ['nullable', 'array', 'max:3'],
            'incident_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'incident_camera_images' => ['nullable', 'array', 'max:3'],
            'incident_camera_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);

        $removeImageIds = collect($data['remove_image_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $imagesToRemove = $incidentReport->images
            ->whereIn('id', $removeImageIds)
            ->values();
        $newImageFiles = $this->incidentImageFiles($request);
        $legacyImageCount = $incidentReport->images->isEmpty() && filled($incidentReport->image_path) ? 1 : 0;
        $remainingImageCount = $incidentReport->images->count() - $imagesToRemove->count() + $legacyImageCount;
        $totalImageCount = $remainingImageCount + count($newImageFiles);

        if ($totalImageCount < 1) {
            return back()
                ->withInput()
                ->withErrors(['incident_images' => 'Keep at least one incident image or attach a new one before saving.']);
        }

        if ($totalImageCount > 3) {
            return back()
                ->withInput()
                ->withErrors(['incident_images' => 'Keep up to 3 incident images only. Remove an existing image before adding another.']);
        }

        $before = $incidentReport->only(['category', 'priority', 'severity', 'description', 'image_path']);

        DB::transaction(function () use ($data, $incidentReport, $imagesToRemove, $newImageFiles, $remainingImageCount): void {
            $incidentReport->update([
                'title' => $data['category'],
                'incident_type' => $data['category'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'severity' => $this->severityFromPriority($data['priority']),
                'description' => $data['description'],
            ]);

            foreach ($imagesToRemove as $image) {
                $this->deleteIncidentImage($image);
            }

            $this->storeIncidentImages($incidentReport, $newImageFiles, $remainingImageCount);
            $this->reorderIncidentImages($incidentReport);

            $firstImagePath = $incidentReport->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('image_path');

            $incidentReport->update([
                'image_path' => $firstImagePath ?: $incidentReport->image_path,
            ]);
        });

        $incidentReport->refresh();

        AuditLogger::record('incident_guard_updated', 'Incident report updated by reporting guard before supervisor review.', $incidentReport, [
            'before' => $before,
            'after' => $incidentReport->only(['category', 'priority', 'severity', 'description', 'image_path']),
            'removed_images' => $imagesToRemove->count(),
            'added_images' => count($newImageFiles),
        ]);

        return redirect()
            ->route('patrol-logs.index')
            ->with('status', 'Incident report updated successfully.');
    }

    public function downloadPdf(Request $request, IncidentReport $incidentReport): Response
    {
        $this->ensureCanDownloadIncident($request, $incidentReport);

        $incidentReport->load(['securityGuard', 'checkpoint', 'patrolLog', 'images']);
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('system.incidents.pdf', [
            'generatedAt' => now(config('app.timezone')),
            'imageDataUris' => $this->imageDataUris($incidentReport),
            'incident' => $incidentReport,
            'incidentFormPageOneDataUri' => $this->incidentFormDataUri('security-incident-report-format-page-1.png'),
            'incidentFormPageTwoDataUri' => $this->incidentFormDataUri('security-incident-report-format-page-2.png'),
        ])->setPaper([0, 0, 612, 936]);

        $filename = $this->pdfFilename($incidentReport);

        if ($request->boolean('print') || $request->boolean('preview')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    public function image(Request $request, IncidentReport $incidentReport, IncidentReportImage $incidentReportImage): Response
    {
        abort_unless($incidentReportImage->incident_report_id === $incidentReport->id, 404);

        $this->ensureCanDownloadIncident($request, $incidentReport);

        $contents = null;
        $mimeType = $incidentReportImage->mime_type ?: 'image/jpeg';

        if ($incidentReportImage->image_path && Storage::disk('public')->exists($incidentReportImage->image_path)) {
            $contents = Storage::disk('public')->get($incidentReportImage->image_path);
            $mimeType = Storage::disk('public')->mimeType($incidentReportImage->image_path) ?: $mimeType;
        } elseif ($incidentReportImage->image_data) {
            $decoded = base64_decode($incidentReportImage->image_data, true);
            $contents = $decoded === false ? null : $decoded;
        }

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function ensureCanDownloadIncident(Request $request, IncidentReport $incidentReport): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        $guardId = $request->user()->guardProfile?->id;

        abort_unless($guardId && $incidentReport->guard_id === $guardId, 403);
    }

    private function ensureGuardCanEditIncident(Request $request, IncidentReport $incidentReport): void
    {
        abort_unless($request->user()?->role === 'guard', 403);

        abort_unless($incidentReport->canBeEditedByGuard($request->user()->guardProfile), 403);
    }

    private function incidentImageFiles(Request $request): array
    {
        return collect([
            ...$this->uploadedFilesWithSource($request->file('incident_images', []), 'upload'),
            ...$this->uploadedFilesWithSource($request->file('incident_camera_images', []), 'camera'),
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

    private function storeIncidentImages(IncidentReport $incidentReport, array $incidentImageFiles, int $existingCount = 0): void
    {
        foreach (array_slice($incidentImageFiles, 0, 3) as $index => $item) {
            $file = $item['file'];
            $image = $this->compressedUploadedImage($file);
            $path = 'incident-reports/'.Str::uuid().'.'.$image['extension'];

            Storage::disk('public')->put($path, $image['contents']);

            $incidentReport->images()->create([
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $image['mime_type'],
                'image_data' => base64_encode($image['contents']),
                'source' => $item['source'],
                'sort_order' => $existingCount + $index + 1,
            ]);
        }
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

    private function deleteIncidentImage(IncidentReportImage $image): void
    {
        if ($image->image_path) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();
    }

    private function reorderIncidentImages(IncidentReport $incidentReport): void
    {
        $incidentReport->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(fn (IncidentReportImage $image, int $index) => $image->update([
                'sort_order' => $index + 1,
            ]));
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

    private function imageDataUris(IncidentReport $incidentReport): array
    {
        $imagePaths = $incidentReport->images
            ->pluck('image_path')
            ->filter()
            ->values();

        if ($imagePaths->isEmpty() && $incidentReport->image_path) {
            $imagePaths = collect([$incidentReport->image_path]);
        }

        if ($incidentReport->images->isNotEmpty()) {
            return $incidentReport->images
                ->map(fn (IncidentReportImage $image) => $this->imageDataUriFromImage($image))
                ->filter()
                ->values()
                ->all();
        }

        return $imagePaths
            ->map(function ($path) {
                return $this->imageDataUriFromPath($path);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function imageDataUriFromImage(IncidentReportImage $image): ?string
    {
        if ($image->image_path) {
            $dataUri = $this->imageDataUriFromPath($image->image_path);

            if ($dataUri) {
                return $dataUri;
            }
        }

        if (! $image->image_data) {
            return null;
        }

        $contents = base64_decode($image->image_data, true);

        if ($contents === false) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $image->mime_type ?: 'image/jpeg', base64_encode($contents));
    }

    private function imageDataUriFromPath(string $path): ?string
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }

    private function incidentFormDataUri(string $filename): ?string
    {
        $path = public_path('images/'.$filename);

        if (! file_exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    private function pdfFilename(IncidentReport $incidentReport): string
    {
        $category = Str::slug($incidentReport->category ?: 'incident-report');

        return sprintf('incident-report-%s-%06d.pdf', $category, $incidentReport->id);
    }
}

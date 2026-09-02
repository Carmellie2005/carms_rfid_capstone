<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\IncidentReportImage;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            ->paginate(10)
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

    public function downloadPdf(Request $request, IncidentReport $incidentReport): Response
    {
        $this->ensureCanDownloadIncident($request, $incidentReport);

        $incidentReport->load(['securityGuard', 'checkpoint', 'patrolLog', 'images']);
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('system.incidents.pdf', [
            'generatedAt' => now(config('app.timezone')),
            'imageDataUris' => $this->imageDataUris($incidentReport),
            'incident' => $incidentReport,
            'letterheadDataUri' => $this->letterheadDataUri(),
        ])->setPaper('a4');

        return $pdf->download($this->pdfFilename($incidentReport));
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

    private function letterheadDataUri(): ?string
    {
        $path = public_path('images/pdf-letterhead.png');

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

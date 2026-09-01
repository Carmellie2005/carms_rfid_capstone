<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
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

        return $imagePaths
            ->filter(fn ($path) => Storage::disk('public')->exists($path))
            ->map(function ($path) {
                $mimeType = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
                $contents = Storage::disk('public')->get($path);

                return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
            })
            ->values()
            ->all();
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

<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ChecklistProofPhoto;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PatrolLogController extends Controller
{
    public function index(Request $request): View
    {
        $isSupervisor = $request->user()->role === 'admin';
        $guardProfile = $request->user()->guardProfile;

        $logs = $this->patrolLogQuery($request, $isSupervisor, $guardProfile)
            ->latest('scanned_at')
            ->paginate($isSupervisor ? 12 : 6)
            ->withQueryString();

        return view('system.patrols.index', [
            'logs' => $logs,
            'guards' => $isSupervisor ? Guard::orderBy('name')->get() : collect([$guardProfile])->filter(),
            'checkpoints' => Checkpoint::orderBy('name')->get(),
            'isSupervisor' => $isSupervisor,
        ]);
    }

    public function downloadPdf(Request $request, PatrolLog $patrolLog): Response
    {
        $this->ensureCanViewPatrolLog($request, $patrolLog);

        $patrolLog->load(['securityGuard', 'checkpoint', 'checklistResponse.proofPhotos', 'incidentReport']);
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('system.patrols.pdf', [
            'generatedAt' => now(config('app.timezone')),
            'imageDataUris' => $this->patrolImageDataUris($patrolLog),
            'patrolLog' => $patrolLog,
        ])->setPaper([0, 0, 595.28, 841.89]);

        $filename = $this->pdfFilename($patrolLog);

        $response = $request->boolean('print') || $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function areaSelfie(Request $request, PatrolLog $patrolLog): Response
    {
        $this->ensureCanViewPatrolLog($request, $patrolLog);

        $contents = null;
        $mimeType = $patrolLog->area_selfie_mime_type ?: 'image/jpeg';

        if ($patrolLog->area_selfie_path && Storage::disk('public')->exists($patrolLog->area_selfie_path)) {
            $contents = Storage::disk('public')->get($patrolLog->area_selfie_path);
            $mimeType = Storage::disk('public')->mimeType($patrolLog->area_selfie_path) ?: $mimeType;
        } elseif ($patrolLog->area_selfie_image_data) {
            $decoded = base64_decode($patrolLog->area_selfie_image_data, true);
            $contents = $decoded === false ? null : $decoded;
        }

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function proofPhoto(Request $request, PatrolLog $patrolLog, ChecklistProofPhoto $checklistProofPhoto): Response
    {
        abort_unless($checklistProofPhoto->patrol_log_id === $patrolLog->id, 404);

        $this->ensureCanViewPatrolLog($request, $patrolLog);

        $contents = null;
        $mimeType = $checklistProofPhoto->mime_type ?: 'image/jpeg';

        if ($checklistProofPhoto->image_path && Storage::disk('public')->exists($checklistProofPhoto->image_path)) {
            $contents = Storage::disk('public')->get($checklistProofPhoto->image_path);
            $mimeType = Storage::disk('public')->mimeType($checklistProofPhoto->image_path) ?: $mimeType;
        } elseif ($checklistProofPhoto->image_data) {
            $decoded = base64_decode($checklistProofPhoto->image_data, true);
            $contents = $decoded === false ? null : $decoded;
        }

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function patrolLogQuery(Request $request, bool $isSupervisor, ?Guard $guardProfile): Builder
    {
        return PatrolLog::with(['securityGuard', 'checkpoint', 'checklistResponse.proofPhotos', 'incidentReport'])
            ->whereNotIn('status', ['pending_face', 'pending_selfie'])
            ->when(! $isSupervisor, fn (Builder $query) => $query->where('guard_id', $guardProfile?->id ?? 0))
            ->when($isSupervisor && $request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($isSupervisor && $request->filled('guard_id'), fn (Builder $query) => $query->where('guard_id', $request->integer('guard_id')))
            ->when(! $isSupervisor && $request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('checkpoint_id'), fn (Builder $query) => $query->where('checkpoint_id', $request->integer('checkpoint_id')))
            ->when($request->filled('date'), function (Builder $query) use ($request) {
                $date = Carbon::parse($request->date('date')->toDateString(), config('app.timezone'));

                $query->whereBetween('scanned_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ]);
            });
    }

    private function ensureCanViewPatrolLog(Request $request, PatrolLog $patrolLog): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        $guardId = $request->user()->guardProfile?->id;

        abort_unless($guardId && $patrolLog->guard_id === $guardId, 403);
    }

    private function patrolImageDataUris(PatrolLog $patrolLog): array
    {
        $areaSelfie = $this->imageDataUriFromPatrolSelfie($patrolLog);
        $proofPhotos = $patrolLog->checklistResponse?->proofPhotos ?? collect();

        return collect([$areaSelfie])
            ->filter()
            ->merge(
                $proofPhotos
                    ->map(fn (ChecklistProofPhoto $photo) => $this->imageDataUriFromProofPhoto($photo))
                    ->filter()
            )
            ->values()
            ->all();
    }

    private function imageDataUriFromPatrolSelfie(PatrolLog $patrolLog): ?array
    {
        if ($patrolLog->area_selfie_path) {
            $dataUri = $this->imageDataUriFromPath($patrolLog->area_selfie_path);

            if ($dataUri) {
                return $dataUri;
            }
        }

        return $this->imageDataUriFromBase64(
            $patrolLog->area_selfie_image_data,
            $patrolLog->area_selfie_mime_type ?: 'image/jpeg',
        );
    }

    private function imageDataUriFromProofPhoto(ChecklistProofPhoto $photo): ?array
    {
        if ($photo->image_path) {
            $dataUri = $this->imageDataUriFromPath($photo->image_path);

            if ($dataUri) {
                return $dataUri;
            }
        }

        return $this->imageDataUriFromBase64($photo->image_data, $photo->mime_type ?: 'image/jpeg');
    }

    private function imageDataUriFromPath(string $path): ?array
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        return $this->imageDataUriFromContents($contents, $mimeType);
    }

    private function imageDataUriFromBase64(?string $imageData, string $mimeType): ?array
    {
        if (! $imageData) {
            return null;
        }

        $contents = base64_decode($imageData, true);

        if ($contents === false) {
            return null;
        }

        return $this->imageDataUriFromContents($contents, $mimeType);
    }

    private function imageDataUriFromContents(string $contents, string $mimeType): array
    {
        $dimensions = @getimagesizefromstring($contents) ?: [];

        return [
            'src' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents)),
            'width' => isset($dimensions[0]) ? (int) $dimensions[0] : null,
            'height' => isset($dimensions[1]) ? (int) $dimensions[1] : null,
        ];
    }

    private function pdfFilename(PatrolLog $patrolLog): string
    {
        $checkpoint = Str::slug($patrolLog->checkpoint?->code ?: $patrolLog->checkpoint_code ?: 'patrol-log');

        return sprintf('patrol-log-%s-%06d.pdf', $checkpoint, $patrolLog->id);
    }

}

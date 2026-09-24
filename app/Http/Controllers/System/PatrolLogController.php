<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ChecklistProofPhoto;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\PatrolLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

}

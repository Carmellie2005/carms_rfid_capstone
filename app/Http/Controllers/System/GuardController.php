<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\User;
use App\Rules\UsernameOrEmail;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class GuardController extends Controller
{
    private const DEFAULT_SHIFT = 'Night Shift';

    public function index(): View
    {
        return view('system.guards.index', [
            'guards' => Guard::with('user')
                ->with('faceDescriptors:id,guard_id,descriptor')
                ->withCount('faceDescriptors')
                ->where('employee_no', '!=', 'UNKNOWN')
                ->latest()
                ->paginate(10),
            'newGuard' => new Guard([
                'shift' => self::DEFAULT_SHIFT,
                'status' => 'active',
            ]),
        ]);
    }

    public function records(Guard $guard): JsonResponse
    {
        abort_if($guard->employee_no === 'UNKNOWN', 404);

        $guard->loadMissing('user');

        $patrolLogs = $guard->patrolLogs()
            ->with([
                'checkpoint:id,code,name,location',
                'checklistResponse:id,patrol_log_id,remarks',
                'incidentReport:id,patrol_log_id,title,status,priority',
            ])
            ->latest('scanned_at')
            ->limit(10)
            ->get();

        $incidents = $guard->incidentReports()
            ->with('checkpoint:id,code,name,location')
            ->latest('reported_at')
            ->latest()
            ->limit(5)
            ->get();

        $faceAttempts = $guard->faceVerificationAttempts()
            ->latest()
            ->limit(5)
            ->get(['id', 'status', 'match_distance', 'match_threshold', 'verified_at', 'created_at']);

        return response()->json([
            'guard' => [
                'id' => $guard->id,
                'name' => $guard->name,
                'employee_no' => $guard->employee_no,
                'email' => $guard->email,
                'phone' => $guard->phone,
                'username' => $guard->user?->username,
                'role' => $guard->user?->role,
                'rfid_uid' => $guard->rfid_uid,
                'shift' => $guard->shift,
                'status' => $guard->status,
                'status_label' => $this->labelFor($guard->status),
                'face_registration' => $this->hasCompletedFaceRegistration($guard) ? 'Registered' : 'Not registered',
                'notes' => $guard->notes,
            ],
            'stats' => [
                'total_scans' => $guard->patrolLogs()->count(),
                'completed_patrols' => $guard->patrolLogs()->where('status', 'completed')->count(),
                'suspicious_patrols' => $guard->patrolLogs()->where('status', 'suspicious')->count(),
                'incident_reports' => $guard->incidentReports()->count(),
                'failed_face_attempts' => $guard->faceVerificationAttempts()->where('status', 'failed')->count(),
            ],
            'patrol_logs' => $patrolLogs->map(fn ($log) => [
                'id' => $log->id,
                'scanned_at' => $this->formatDate($log->scanned_at),
                'checkpoint' => $log->checkpoint?->name ?? $log->checkpoint_code ?? 'Unknown checkpoint',
                'checkpoint_code' => $log->checkpoint?->code ?? $log->checkpoint_code,
                'rfid_status' => $log->rfid_status,
                'rfid_status_label' => $this->labelFor($log->rfid_status),
                'facial_status' => $log->facial_status,
                'facial_status_label' => $this->labelFor($log->facial_status),
                'status' => $log->status,
                'status_label' => $this->labelFor($log->status),
                'remarks' => $log->checklistResponse?->remarks,
                'incident_title' => $log->incidentReport?->title,
                'incident_status' => $log->incidentReport?->status,
            ]),
            'incidents' => $incidents->map(fn ($incident) => [
                'id' => $incident->id,
                'title' => $incident->title,
                'type' => $incident->incident_type ?? $incident->category,
                'priority' => $incident->priority ?? $incident->severity,
                'priority_label' => $this->labelFor($incident->priority ?? $incident->severity),
                'status' => $incident->status,
                'status_label' => $this->labelFor($incident->status),
                'checkpoint' => $incident->checkpoint?->name ?? 'Unknown checkpoint',
                'reported_at' => $this->formatDate($incident->reported_at ?? $incident->created_at),
            ]),
            'face_attempts' => $faceAttempts->map(fn ($attempt) => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'status_label' => $this->labelFor($attempt->status),
                'match_distance' => $attempt->match_distance,
                'match_threshold' => $attempt->match_threshold,
                'verified_at' => $this->formatDate($attempt->verified_at),
                'created_at' => $this->formatDate($attempt->created_at),
            ]),
        ]);
    }

    public function create(): View
    {
        return view('system.guards.form', [
            'guard' => new Guard([
                'shift' => self::DEFAULT_SHIFT,
                'status' => 'active',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $guard = null;

        DB::transaction(function () use ($validated, &$guard) {
            $user = $this->saveGuardAccount(null, $validated['guard'], $validated['account']);
            $guardData = $validated['guard'];
            $guardData['user_id'] = $user->id;

            $guard = Guard::create($guardData);
        });

        AuditLogger::record('guard_created', 'Guard profile and login account created.', $guard, [
            'employee_no' => $guard?->employee_no,
            'rfid_uid' => $guard?->rfid_uid,
            'status' => $guard?->status,
        ]);

        return redirect()->route('guards.index')->with('status', 'Guard profile and login account created.');
    }

    public function edit(Guard $guard): View
    {
        $guard->loadMissing('user');

        return view('system.guards.form', compact('guard'));
    }

    public function update(Request $request, Guard $guard): RedirectResponse
    {
        $validated = $this->validatedData($request, $guard);
        $before = $guard->only(['employee_no', 'name', 'email', 'phone', 'rfid_uid', 'shift', 'status']);

        DB::transaction(function () use ($guard, $validated) {
            $guard->loadMissing('user');

            $user = $this->saveGuardAccount($guard->user, $validated['guard'], $validated['account']);
            $guardData = $validated['guard'];
            $guardData['user_id'] = $user->id;

            $guard->update($guardData);
        });

        AuditLogger::record('guard_updated', 'Guard profile and login account updated.', $guard, [
            'before' => $before,
            'after' => $guard->fresh()?->only(['employee_no', 'name', 'email', 'phone', 'rfid_uid', 'shift', 'status']),
        ]);

        return redirect()->route('guards.index')->with('status', 'Guard profile and login account updated.');
    }

    public function destroy(Guard $guard): RedirectResponse
    {
        AuditLogger::record('guard_deleted', 'Guard profile and login account removed.', $guard, [
            'employee_no' => $guard->employee_no,
            'rfid_uid' => $guard->rfid_uid,
            'status' => $guard->status,
        ]);

        DB::transaction(function () use ($guard) {
            $guard->loadMissing('user');
            $user = $guard->user;

            $guard->delete();

            if ($user && $user->role === 'guard') {
                $user->delete();
            }
        });

        return redirect()->route('guards.index')->with('status', 'Guard profile and login account removed.');
    }

    private function validatedData(Request $request, ?Guard $guard = null): array
    {
        $guardId = $guard?->id;
        $userId = $guard?->user_id;
        $passwordRules = $guard?->user_id
            ? ['nullable', 'confirmed', Password::min(8)]
            : ['required', 'confirmed', Password::min(8)];

        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:50', Rule::unique('guards', 'employee_no')->ignore($guardId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'rfid_uid' => ['required', 'string', 'max:100', Rule::unique('guards', 'rfid_uid')->ignore($guardId)],
            'face_reference' => ['nullable', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'username' => ['required', 'string', 'max:255', new UsernameOrEmail, Rule::unique('users', 'username')->ignore($userId), Rule::unique('users', 'email')->ignore($userId)],
            'password' => $passwordRules,
        ]);

        $username = Str::lower(trim($data['username']));

        $guardData = collect($data)->only([
            'employee_no',
            'name',
            'email',
            'phone',
            'rfid_uid',
            'face_reference',
            'shift',
            'status',
            'notes',
        ])->all();

        $guardData['email'] = filled($guardData['email'] ?? null) ? Str::lower(trim($guardData['email'])) : null;
        $guardData['rfid_uid'] = strtoupper(trim($guardData['rfid_uid']));
        $guardData['shift'] = filled($guardData['shift'] ?? null) ? trim($guardData['shift']) : self::DEFAULT_SHIFT;

        return [
            'guard' => $guardData,
            'account' => [
                'username' => $username,
                'email' => $this->accountEmail($guardData['email'], $username),
                'password' => $data['password'] ?? null,
            ],
        ];
    }

    private function saveGuardAccount(?User $user, array $guardData, array $accountData): User
    {
        $user ??= new User();

        $user->forceFill([
            'name' => $guardData['name'],
            'username' => $accountData['username'],
            'email' => $accountData['email'],
            'role' => 'guard',
        ]);

        if (filled($accountData['password'])) {
            $user->password = Hash::make($accountData['password']);
        }

        $user->save();

        return $user;
    }

    private function accountEmail(?string $email, string $username): string
    {
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $username;
        }

        if (filled($email)) {
            return $email;
        }

        return "{$username}@guards.campusrfid.local";
    }

    private function hasCompletedFaceRegistration(Guard $guard): bool
    {
        return $guard->faceDescriptors()
            ->get(['descriptor'])
            ->contains(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128);
    }

    private function labelFor(?string $value): string
    {
        return Str::of($value ?: 'unknown')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function formatDate($date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('M d, Y h:i A');
    }
}

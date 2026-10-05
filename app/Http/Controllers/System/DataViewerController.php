<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\ChecklistResponse;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\PatrolChecklist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DataViewerController extends Controller
{
    private const DEFAULT_DATASET = 'patrol_logs';

    public function __invoke(Request $request): View
    {
        $datasets = $this->datasets();
        $dataset = array_key_exists($request->query('dataset'), $datasets)
            ? (string) $request->query('dataset')
            : self::DEFAULT_DATASET;

        return view('system.data-viewer.index', [
            'datasets' => $datasets,
            'datasetCounts' => $this->datasetCounts(),
            'activeDataset' => $dataset,
            'activeConfig' => $datasets[$dataset],
            'records' => $this->recordsFor($dataset, $request),
            'filters' => [
                'q' => $this->searchTerm($request),
                'status' => (string) $request->query('status', ''),
                'date' => $this->dateFilter($request),
            ],
        ]);
    }

    private function recordsFor(string $dataset, Request $request): LengthAwarePaginator
    {
        return match ($dataset) {
            'users' => $this->users($request),
            'guards' => $this->guards($request),
            'checkpoints' => $this->checkpoints($request),
            'incidents' => $this->incidents($request),
            'checklists' => $this->checklists($request),
            'audit_logs' => $this->auditLogs($request),
            default => $this->patrolLogs($request),
        };
    }

    private function users(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $role = (string) $request->query('status', '');

        return User::query()
            ->with('guardProfile')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }))
            ->when(in_array($role, ['admin', 'guard'], true), fn (Builder $query) => $query->where('role', $role))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $this->textCell('#'.$user->id),
                'name' => $this->textCell($user->name, 'Contact details hidden'),
                'username' => $this->sensitiveCell($user->username ?: $user->email, 'Login identifier masked'),
                'role' => $this->badgeCell($user->role === 'admin' ? 'Supervisor' : 'Security Guard', $user->role === 'admin' ? 'info' : 'neutral'),
                'linked_record' => $this->textCell($user->guardProfile?->name ?: 'Not linked', $user->guardProfile?->employee_no),
                'created_at' => $this->dateCell($user->created_at),
            ]);
    }

    private function guards(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $status = (string) $request->query('status', '');

        return Guard::query()
            ->with('user')
            ->withCount(['patrolLogs', 'incidentReports'])
            ->where('employee_no', '!=', 'UNKNOWN')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%")
                    ->orWhere('rfid_uid', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Guard $guard) => [
                'employee' => $this->textCell($guard->name, $guard->employee_no),
                'rfid_uid' => $this->sensitiveCell($guard->rfid_uid, 'RFID UID masked'),
                'account' => $this->textCell($guard->user ? 'Linked account' : 'No login account', 'Login details hidden'),
                'records' => $this->textCell($guard->patrol_logs_count.' patrols', $guard->incident_reports_count.' incidents'),
                'status' => $this->badgeCell(Str::headline($guard->status), $guard->status === 'active' ? 'success' : 'neutral'),
            ]);
    }

    private function checkpoints(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $status = (string) $request->query('status', '');

        return Checkpoint::query()
            ->withCount(['patrolLogs', 'incidentReports'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('device_uid', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Checkpoint $checkpoint) => [
                'checkpoint' => $this->textCell($checkpoint->name, $checkpoint->code),
                'location' => $this->textCell($checkpoint->location),
                'device' => $this->sensitiveCell($checkpoint->device_uid, 'Device UID masked'),
                'reader' => $this->textCell(
                    $checkpoint->reader_last_seen_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?: 'No heartbeat yet',
                    $checkpoint->reader_last_status ? Str::headline($checkpoint->reader_last_status) : null
                ),
                'records' => $this->textCell($checkpoint->patrol_logs_count.' patrols', $checkpoint->incident_reports_count.' incidents'),
                'status' => $this->badgeCell(Str::headline($checkpoint->status), $checkpoint->status === 'active' ? 'success' : 'neutral'),
            ]);
    }

    private function patrolLogs(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $status = (string) $request->query('status', '');
        $date = $this->dateFilter($request);

        return PatrolLog::query()
            ->with(['securityGuard', 'checkpoint', 'checklistResponse.proofPhotos', 'incidentReport'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('rfid_uid', 'like', "%{$search}%")
                    ->orWhere('checkpoint_code', 'like', "%{$search}%")
                    ->orWhereHas('securityGuard', fn (Builder $guardQuery) => $guardQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%"))
                    ->orWhereHas('checkpoint', fn (Builder $checkpointQuery) => $checkpointQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            }))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($date !== '', fn (Builder $query) => $this->whereInDay($query, 'scanned_at', $date))
            ->latest('scanned_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (PatrolLog $patrolLog) => [
                'time' => $this->dateCell($patrolLog->scanned_at),
                'guard' => $this->textCell($patrolLog->securityGuard?->name ?: 'Unknown guard', $patrolLog->securityGuard?->employee_no ?? 'No guard match'),
                'checkpoint' => $this->textCell($patrolLog->checkpoint?->name ?: 'Unknown checkpoint', $patrolLog->checkpoint_code),
                'rfid' => $this->sensitiveCell($patrolLog->rfid_uid, Str::headline($patrolLog->rfid_status)),
                'status' => $this->badgeCell($this->statusLabel($patrolLog->status), $this->toneForStatus($patrolLog->status)),
                'checklist' => $this->textCell($patrolLog->checklistSummary(), $patrolLog->checklistPhotoCount().' proof photos'),
            ]);
    }

    private function incidents(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $status = (string) $request->query('status', '');
        $date = $this->dateFilter($request);

        return IncidentReport::query()
            ->with(['securityGuard', 'checkpoint', 'images'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('category', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('securityGuard', fn (Builder $guardQuery) => $guardQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%"))
                    ->orWhereHas('checkpoint', fn (Builder $checkpointQuery) => $checkpointQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            }))
            ->when(in_array($status, ['submitted', 'under_review', 'resolved'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($date !== '', fn (Builder $query) => $this->whereInDay($query, 'incident_at', $date))
            ->latest('incident_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (IncidentReport $incident) => [
                'time' => $this->dateCell($incident->incident_at),
                'category' => $this->textCell($incident->category, $incident->location),
                'guard' => $this->textCell($incident->securityGuard?->name ?: 'Unknown guard', $incident->securityGuard?->employee_no),
                'checkpoint' => $this->textCell($incident->checkpoint?->name ?: 'Unassigned', $incident->checkpoint?->code),
                'priority' => $this->badgeCell(Str::headline($incident->priority), $this->toneForPriority($incident->priority)),
                'status' => $this->badgeCell($this->statusLabel($incident->status), $this->toneForStatus($incident->status)),
                'photos' => $this->textCell((string) $incident->images->count(), 'incident photos'),
            ]);
    }

    private function checklists(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $date = $this->dateFilter($request);

        return ChecklistResponse::query()
            ->with(['patrolLog.securityGuard', 'patrolLog.checkpoint', 'proofPhotos'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('remarks', 'like', "%{$search}%")
                    ->orWhereHas('patrolLog.securityGuard', fn (Builder $guardQuery) => $guardQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%"))
                    ->orWhereHas('patrolLog.checkpoint', fn (Builder $checkpointQuery) => $checkpointQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            }))
            ->when($date !== '', fn (Builder $query) => $this->whereInDay($query, 'created_at', $date))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(function (ChecklistResponse $checklist) {
                $items = PatrolChecklist::statusSummaries($checklist);
                $checkedCount = $items->where('status', PatrolChecklist::STATUS_NORMAL)->count();
                $issueCount = $items->where('status', PatrolChecklist::STATUS_ISSUE)->count();

                return [
                    'time' => $this->dateCell($checklist->created_at),
                    'guard' => $this->textCell($checklist->patrolLog?->securityGuard?->name ?: 'Unknown guard', $checklist->patrolLog?->securityGuard?->employee_no),
                    'checkpoint' => $this->textCell($checklist->patrolLog?->checkpoint?->name ?: 'Unknown checkpoint', $checklist->patrolLog?->checkpoint?->code),
                    'result' => $this->textCell($checkedCount.' checked', $issueCount > 0 ? $issueCount.' issues found' : 'No issue marked'),
                    'photos' => $this->textCell((string) $checklist->proofPhotos->count(), 'proof photos'),
                    'remarks' => $this->textCell(Str::limit($checklist->remarks ?: 'No remarks', 80)),
                ];
            });
    }

    private function auditLogs(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $date = $this->dateFilter($request);

        return AuditLog::query()
            ->with('user')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            }))
            ->when($date !== '', fn (Builder $query) => $this->whereInDay($query, 'created_at', $date))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (AuditLog $auditLog) => [
                'time' => $this->dateCell($auditLog->created_at),
                'actor' => $this->textCell($auditLog->actor_name ?: $auditLog->user?->name ?: 'System'),
                'action' => $this->badgeCell(Str::headline($auditLog->action), $this->toneForStatus($auditLog->resultLabel())),
                'description' => $this->textCell(Str::limit($auditLog->description, 90), 'Full technical details stay in Audit Trail'),
                'subject' => $this->textCell(class_basename((string) $auditLog->subject_type) ?: 'No subject', $auditLog->subject_id ? '#'.$auditLog->subject_id : null),
            ]);
    }

    private function datasets(): array
    {
        return [
            'users' => [
                'label' => 'Users',
                'description' => 'Login accounts',
                'columns' => ['id' => 'ID', 'name' => 'Name', 'username' => 'Username', 'role' => 'Role', 'linked_record' => 'Linked Record', 'created_at' => 'Created'],
                'filter_label' => 'Role',
                'filter_options' => ['admin' => 'Supervisor', 'guard' => 'Security Guard'],
            ],
            'guards' => [
                'label' => 'Guards',
                'description' => 'Guard profiles and RFID cards',
                'columns' => ['employee' => 'Employee', 'rfid_uid' => 'RFID UID', 'account' => 'Account', 'records' => 'Records', 'status' => 'Status'],
                'filter_label' => 'Status',
                'filter_options' => ['active' => 'Active', 'inactive' => 'Inactive'],
            ],
            'checkpoints' => [
                'label' => 'Checkpoints',
                'description' => 'Reader and checkpoint records',
                'columns' => ['checkpoint' => 'Checkpoint', 'location' => 'Location', 'device' => 'Device', 'reader' => 'Reader Last Seen', 'records' => 'Records', 'status' => 'Status'],
                'filter_label' => 'Status',
                'filter_options' => ['active' => 'Active', 'inactive' => 'Inactive'],
            ],
            'patrol_logs' => [
                'label' => 'Patrol Logs',
                'description' => 'RFID scans and patrol submissions',
                'columns' => ['time' => 'Time', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'rfid' => 'RFID', 'status' => 'Status', 'checklist' => 'Checklist'],
                'filter_label' => 'Status',
                'filter_options' => [
                    'valid' => 'Valid',
                    'suspicious' => 'Suspicious',
                    'invalid' => 'Invalid',
                    'pending_selfie' => 'Pending Selfie',
                    'pending_checklist' => 'Pending Checklist',
                    'profile_incomplete' => 'Profile Incomplete',
                    'outside_schedule' => 'Outside Schedule',
                    'expired' => 'Expired',
                ],
            ],
            'incidents' => [
                'label' => 'Incidents',
                'description' => 'Submitted incident reports',
                'columns' => ['time' => 'Time', 'category' => 'Category', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'priority' => 'Priority', 'status' => 'Status', 'photos' => 'Photos'],
                'filter_label' => 'Status',
                'filter_options' => ['submitted' => 'Submitted', 'under_review' => 'Under Review', 'resolved' => 'Resolved'],
            ],
            'checklists' => [
                'label' => 'Checklist',
                'description' => 'Patrol checklist answers',
                'columns' => ['time' => 'Time', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'result' => 'Result', 'photos' => 'Photos', 'remarks' => 'Remarks'],
                'filter_label' => null,
                'filter_options' => [],
            ],
            'audit_logs' => [
                'label' => 'Audit Trail',
                'description' => 'System activity logs',
                'columns' => ['time' => 'Time', 'actor' => 'Actor', 'action' => 'Action', 'description' => 'Description', 'subject' => 'Subject'],
                'filter_label' => null,
                'filter_options' => [],
            ],
        ];
    }

    private function datasetCounts(): array
    {
        return [
            'users' => User::count(),
            'guards' => Guard::where('employee_no', '!=', 'UNKNOWN')->count(),
            'checkpoints' => Checkpoint::count(),
            'patrol_logs' => PatrolLog::count(),
            'incidents' => IncidentReport::count(),
            'checklists' => ChecklistResponse::count(),
            'audit_logs' => AuditLog::count(),
        ];
    }

    private function searchTerm(Request $request): string
    {
        return Str::limit(trim((string) $request->query('q', '')), 100, '');
    }

    private function dateFilter(Request $request): string
    {
        $date = (string) $request->query('date', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    private function whereInDay(Builder $query, string $column, string $date): void
    {
        $day = Carbon::parse($date, config('app.timezone'));

        $query->whereBetween($column, [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    private function textCell(?string $value, ?string $subvalue = null, bool $mono = false): array
    {
        return [
            'type' => 'text',
            'value' => filled($value) ? $value : 'N/A',
            'subvalue' => filled($subvalue) ? $subvalue : null,
            'mono' => $mono,
        ];
    }

    private function sensitiveCell(?string $value, ?string $subvalue = null): array
    {
        return $this->textCell($this->maskedValue($value), $subvalue, true);
    }

    private function maskedValue(?string $value, int $visibleCharacters = 4): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Hidden';
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $localPrefix = substr($local, 0, min(2, strlen($local)));

            return $localPrefix.'***@'.$domain;
        }

        $length = strlen($value);

        if ($length <= $visibleCharacters) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', min(8, $length - $visibleCharacters)).substr($value, -$visibleCharacters);
    }

    private function dateCell(mixed $date): array
    {
        $date = $date ? Carbon::parse($date)->timezone(config('app.timezone')) : null;

        return $this->textCell(
            $date?->format('M d, Y') ?? 'No date',
            $date?->format('h:i A')
        );
    }

    private function badgeCell(?string $value, string $tone = 'neutral'): array
    {
        return [
            'type' => 'badge',
            'value' => filled($value) ? $value : 'Recorded',
            'tone' => $tone,
        ];
    }

    private function statusLabel(?string $status): string
    {
        return Str::headline((string) $status ?: 'recorded');
    }

    private function toneForStatus(?string $status): string
    {
        return match (Str::of((string) $status)->lower()->replace(' ', '_')->toString()) {
            'active', 'valid', 'completed', 'resolved', 'success' => 'success',
            'submitted', 'under_review', 'pending_selfie', 'pending_checklist', 'profile_incomplete', 'outside_schedule', 'suspicious' => 'warning',
            'invalid', 'failed', 'critical', 'inactive' => 'danger',
            default => 'neutral',
        };
    }

    private function toneForPriority(?string $priority): string
    {
        return match ($priority) {
            'critical', 'high' => 'danger',
            'low' => 'info',
            default => 'neutral',
        };
    }
}

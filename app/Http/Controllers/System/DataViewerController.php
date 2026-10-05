<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\ChecklistProofPhoto;
use App\Models\ChecklistResponse;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\IncidentReportImage;
use App\Models\PatrolLog;
use App\Models\User;
use App\Support\PatrolChecklist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'photos' => $this->photos($request),
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
                'name' => $this->textCell($user->name, $user->email),
                'username' => $this->textCell($user->username ?: 'No username'),
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
                'contact' => $this->textCell($guard->email ?: 'No email', $guard->phone ?: 'No phone'),
                'rfid_uid' => $this->textCell($guard->rfid_uid, mono: true),
                'account' => $this->textCell($guard->user?->username ?: 'No login account', $guard->user?->email),
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
                'device' => $this->textCell($checkpoint->device_uid ?: 'No device UID', $checkpoint->reader_last_ip ? 'IP '.$checkpoint->reader_last_ip : null, true),
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
                'rfid' => $this->textCell($patrolLog->rfid_uid, Str::headline($patrolLog->rfid_status), true),
                'status' => $this->badgeCell($this->statusLabel($patrolLog->status), $this->toneForStatus($patrolLog->status)),
                'checklist' => $this->textCell($patrolLog->checklistSummary(), $patrolLog->checklistPhotoCount().' proof photos'),
                'action' => $this->linkCell('PDF', route('patrol-logs.pdf', ['patrolLog' => $patrolLog, 'preview' => 1])),
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
                'action' => $this->linkCell('PDF', route('incidents.pdf', ['incidentReport' => $incident, 'preview' => 1])),
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

    private function photos(Request $request): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $type = (string) $request->query('status', '');
        $date = $this->dateFilter($request);

        $query = DB::query()->fromSub($this->photoUnionQuery(), 'photos')
            ->when($search !== '', fn (QueryBuilder $query) => $query->where(function (QueryBuilder $query) use ($search) {
                $query->where('photo_type', 'like', "%{$search}%")
                    ->orWhere('context', 'like', "%{$search}%")
                    ->orWhere('guard_name', 'like', "%{$search}%")
                    ->orWhere('checkpoint_name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            }))
            ->when(in_array($type, ['incident', 'checklist', 'area_selfie'], true), fn (QueryBuilder $query) => $query->where('type_key', $type))
            ->when($date !== '', fn (QueryBuilder $query) => $this->whereInDay($query, 'created_at', $date))
            ->orderByDesc('created_at');

        return $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (object $photo) => [
                'time' => $this->dateCell($photo->created_at ? Carbon::parse($photo->created_at) : null),
                'type' => $this->badgeCell($photo->photo_type, $photo->type_key === 'incident' ? 'danger' : 'info'),
                'context' => $this->textCell($photo->context ?: 'Photo evidence', $photo->original_name ?: Str::headline($photo->source ?: 'camera')),
                'guard' => $this->textCell($photo->guard_name ?: 'Unknown guard', $photo->guard_employee_no),
                'checkpoint' => $this->textCell($photo->checkpoint_name ?: 'Unknown checkpoint', $photo->checkpoint_code),
                'mime' => $this->textCell($photo->mime_type ?: 'image/jpeg', mono: true),
                'action' => $this->linkCell('View', $this->photoUrl($photo)),
            ]);
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
                'description' => $this->textCell(Str::limit($auditLog->description, 90), $auditLog->diagnosticSummary()),
                'subject' => $this->textCell(class_basename((string) $auditLog->subject_type) ?: 'No subject', $auditLog->subject_id ? '#'.$auditLog->subject_id : null),
                'ip' => $this->textCell($auditLog->ip_address ?: 'No IP recorded', mono: true),
            ]);
    }

    private function photoUnionQuery(): QueryBuilder
    {
        $incidentPhotos = DB::table('incident_report_images')
            ->leftJoin('incident_reports', 'incident_report_images.incident_report_id', '=', 'incident_reports.id')
            ->leftJoin('guards', 'incident_reports.guard_id', '=', 'guards.id')
            ->leftJoin('checkpoints', 'incident_reports.checkpoint_id', '=', 'checkpoints.id')
            ->select([
                DB::raw("'incident' as type_key"),
                DB::raw("'Incident photo' as photo_type"),
                'incident_report_images.id',
                'incident_report_images.incident_report_id as parent_id',
                'incident_report_images.created_at',
                'incident_report_images.original_name',
                'incident_report_images.source',
                'incident_report_images.mime_type',
                'incident_reports.category as context',
                'guards.name as guard_name',
                'guards.employee_no as guard_employee_no',
                'checkpoints.name as checkpoint_name',
                'checkpoints.code as checkpoint_code',
            ]);

        $checklistPhotos = DB::table('checklist_proof_photos')
            ->leftJoin('patrol_logs', 'checklist_proof_photos.patrol_log_id', '=', 'patrol_logs.id')
            ->leftJoin('guards', 'patrol_logs.guard_id', '=', 'guards.id')
            ->leftJoin('checkpoints', 'patrol_logs.checkpoint_id', '=', 'checkpoints.id')
            ->select([
                DB::raw("'checklist' as type_key"),
                DB::raw("'Checklist proof' as photo_type"),
                'checklist_proof_photos.id',
                'checklist_proof_photos.patrol_log_id as parent_id',
                'checklist_proof_photos.created_at',
                'checklist_proof_photos.original_name',
                DB::raw("'camera' as source"),
                'checklist_proof_photos.mime_type',
                'checklist_proof_photos.item_label as context',
                'guards.name as guard_name',
                'guards.employee_no as guard_employee_no',
                'checkpoints.name as checkpoint_name',
                'checkpoints.code as checkpoint_code',
            ]);

        $areaSelfies = DB::table('patrol_logs')
            ->leftJoin('guards', 'patrol_logs.guard_id', '=', 'guards.id')
            ->leftJoin('checkpoints', 'patrol_logs.checkpoint_id', '=', 'checkpoints.id')
            ->where(function (QueryBuilder $query) {
                $query->whereNotNull('patrol_logs.area_selfie_path')
                    ->orWhereNotNull('patrol_logs.area_selfie_image_data');
            })
            ->select([
                DB::raw("'area_selfie' as type_key"),
                DB::raw("'Area selfie' as photo_type"),
                'patrol_logs.id',
                'patrol_logs.id as parent_id',
                'patrol_logs.created_at',
                DB::raw("null as original_name"),
                DB::raw("'camera' as source"),
                'patrol_logs.area_selfie_mime_type as mime_type',
                DB::raw("'Patrol area selfie' as context"),
                'guards.name as guard_name',
                'guards.employee_no as guard_employee_no',
                'checkpoints.name as checkpoint_name',
                'checkpoints.code as checkpoint_code',
            ]);

        return $incidentPhotos->unionAll($checklistPhotos)->unionAll($areaSelfies);
    }

    private function photoUrl(object $photo): string
    {
        return match ($photo->type_key) {
            'incident' => route('incidents.images.show', [$photo->parent_id, $photo->id]),
            'checklist' => route('patrol-logs.proof-photos.show', [$photo->parent_id, $photo->id]),
            default => route('patrol-logs.area-selfie.show', $photo->parent_id),
        };
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
                'columns' => ['employee' => 'Employee', 'contact' => 'Contact', 'rfid_uid' => 'RFID UID', 'account' => 'Account', 'records' => 'Records', 'status' => 'Status'],
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
                'columns' => ['time' => 'Time', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'rfid' => 'RFID', 'status' => 'Status', 'checklist' => 'Checklist', 'action' => 'Action'],
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
                'columns' => ['time' => 'Time', 'category' => 'Category', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'priority' => 'Priority', 'status' => 'Status', 'photos' => 'Photos', 'action' => 'Action'],
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
            'photos' => [
                'label' => 'Photos',
                'description' => 'Evidence and proof images',
                'columns' => ['time' => 'Time', 'type' => 'Type', 'context' => 'Context', 'guard' => 'Guard', 'checkpoint' => 'Checkpoint', 'mime' => 'File Type', 'action' => 'Action'],
                'filter_label' => 'Photo Type',
                'filter_options' => ['incident' => 'Incident Photo', 'checklist' => 'Checklist Proof', 'area_selfie' => 'Area Selfie'],
            ],
            'audit_logs' => [
                'label' => 'Audit Trail',
                'description' => 'System activity logs',
                'columns' => ['time' => 'Time', 'actor' => 'Actor', 'action' => 'Action', 'description' => 'Description', 'subject' => 'Subject', 'ip' => 'IP Address'],
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
            'photos' => IncidentReportImage::count()
                + ChecklistProofPhoto::count()
                + PatrolLog::where(fn (Builder $query) => $query
                    ->whereNotNull('area_selfie_path')
                    ->orWhereNotNull('area_selfie_image_data'))
                    ->count(),
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

    private function whereInDay(Builder|QueryBuilder $query, string $column, string $date): void
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

    private function linkCell(string $label, string $href): array
    {
        return [
            'type' => 'link',
            'value' => $label,
            'href' => $href,
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

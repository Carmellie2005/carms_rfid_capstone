<?php

namespace App\Console\Commands;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClearPatrolIncidentRecords extends Command
{
    protected $signature = 'records:clear-patrol-incidents
        {--force : Delete the records. Without this option, the command only previews counts}
        {--unassigned-evidence-only : Delete only incident evidence that is not attached to an incident report}';

    protected $description = 'Clear activity, notification, and enrollment records while keeping guard and user accounts';

    public function handle(): int
    {
        if ($this->option('unassigned-evidence-only')) {
            return $this->clearUnassignedEvidence();
        }

        $scope = $this->scope();
        $plan = $this->deletionPlan($scope);

        $this->line('This command keeps user accounts, guard profiles, and checkpoints.');
        $this->newLine();

        $this->table(['Record type', 'Count'], collect($plan['counts'])
            ->map(fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count])
            ->values()
            ->all());

        $this->line('Files to delete: '.$plan['file_count']);
        $this->line('User accounts kept: '.$plan['kept_users']);
        $this->line('Guard profiles kept: '.$plan['kept_guards']);
        $this->line('Checkpoints kept: '.$plan['kept_checkpoints']);

        if (! $this->option('force')) {
            $this->warn('Dry run only. No records were deleted.');
            $this->line('Run php artisan records:clear-patrol-incidents --force on the hosted server to delete these records.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(fn (): array => $this->deleteRecords($scope));
        $filePaths = $plan['file_paths'];

        if ($filePaths !== []) {
            Storage::disk('public')->delete($filePaths);
        }

        $this->newLine();
        $this->info('Activity, notification, and enrollment records cleared.');
        $this->table(['Deleted record type', 'Count'], collect($deleted)
            ->map(fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count])
            ->values()
            ->all());
        $this->line('Deleted files: '.count($filePaths));
        $this->line('Kept user accounts, guard profiles, and checkpoints.');

        return self::SUCCESS;
    }

    private function clearUnassignedEvidence(): int
    {
        if (! Schema::hasTable('incident_evidence')) {
            $this->warn('The incident_evidence table does not exist. Nothing was deleted.');

            return self::SUCCESS;
        }

        $evidence = DB::table('incident_evidence')
            ->whereNull('incident_report_id')
            ->orderBy('id')
            ->get(['id', 'image_path']);

        $this->line('This cleanup keeps all patrol logs and incident reports.');
        $this->line('Only incident evidence with no attached incident report is included.');
        $this->line('Unassigned evidence photos found: '.$evidence->count());

        if (! $this->option('force')) {
            $this->warn('Dry run only. No evidence was deleted.');
            $this->line('Run php artisan records:clear-patrol-incidents --unassigned-evidence-only --force on the hosted server to delete these photos.');

            return self::SUCCESS;
        }

        $ids = $evidence->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No unassigned incident evidence photos were found.');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($ids): array {
            $rows = DB::table('incident_evidence')
                ->whereNull('incident_report_id')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get(['id', 'image_path']);

            if ($rows->isEmpty()) {
                return ['count' => 0, 'paths' => []];
            }

            $deleted = DB::table('incident_evidence')
                ->whereNull('incident_report_id')
                ->whereIn('id', $rows->pluck('id'))
                ->delete();

            return [
                'count' => $deleted,
                'paths' => $rows->pluck('image_path')->filter()->unique()->values()->all(),
            ];
        });

        if ($result['paths'] !== []) {
            Storage::disk('public')->delete($result['paths']);
        }

        $this->info('Unassigned incident evidence cleanup completed.');
        $this->line('Deleted evidence records: '.$result['count']);
        $this->line('Deleted image files: '.count($result['paths']));
        $this->line('Patrol logs and attached incident reports were kept.');

        return self::SUCCESS;
    }

    private function scope(): array
    {
        $patrolIds = $this->ids('patrol_logs');
        $incidentIds = $this->ids('incident_reports');
        $checklistIds = $this->ids('checklist_responses');
        $incidentEvidenceIds = $this->ids('incident_evidence');

        return compact('patrolIds', 'incidentIds', 'checklistIds', 'incidentEvidenceIds');
    }

    private function deletionPlan(array $scope): array
    {
        $counts = [
            'notification_reads' => $this->tableCount('notification_reads'),
            'system_notifications' => $this->tableCount('system_notifications'),
            'rfid_enrollment_scans' => $this->tableCount('rfid_enrollment_scans'),
            'audit_logs' => $this->tableCount('audit_logs'),
            'incident_images' => $this->countByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
            'incident_evidence' => $scope['incidentEvidenceIds']->count(),
            'incident_reports' => $scope['incidentIds']->count(),
            'checklist_proof_photos' => $this->checklistProofPhotoCount($scope),
            'checklist_responses' => $scope['checklistIds']->count(),
            'patrol_logs' => $scope['patrolIds']->count(),
        ];

        $filePaths = $this->filePaths($scope);

        return [
            'counts' => $counts,
            'file_paths' => $filePaths,
            'file_count' => count($filePaths),
            'kept_users' => $this->tableCount('users'),
            'kept_guards' => $this->tableCount('guards'),
            'kept_checkpoints' => $this->tableCount('checkpoints'),
        ];
    }

    private function deleteRecords(array $scope): array
    {
        return [
            'notification_reads' => $this->deleteTable('notification_reads'),
            'system_notifications' => $this->deleteTable('system_notifications'),
            'rfid_enrollment_scans' => $this->deleteTable('rfid_enrollment_scans'),
            'audit_logs' => $this->deleteTable('audit_logs'),
            'incident_images' => $this->deleteByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
            'incident_reports' => $this->deleteByIds('incident_reports', $scope['incidentIds']),
            'incident_evidence' => $this->deleteByIds('incident_evidence', $scope['incidentEvidenceIds']),
            'checklist_proof_photos' => $this->deleteChecklistProofPhotos($scope),
            'checklist_responses' => $this->deleteByIds('checklist_responses', $scope['checklistIds']),
            'patrol_logs' => $this->deleteByIds('patrol_logs', $scope['patrolIds']),
        ];
    }

    private function ids(string $table): Collection
    {
        if (! $this->hasTableColumn($table, 'id')) {
            return collect();
        }

        return DB::table($table)->pluck('id');
    }

    private function notificationReadQuery(array $scope)
    {
        if (! Schema::hasTable('notification_reads')) {
            return $this->emptyQuery();
        }

        return DB::table('notification_reads')
            ->where(function ($query) use ($scope): void {
                $applied = false;

                if (
                    $this->hasTableColumn('notification_reads', 'notifiable_type')
                    && $this->hasTableColumn('notification_reads', 'notifiable_id')
                    && $scope['patrolIds']->isNotEmpty()
                ) {
                    $query->where(function ($where) use ($scope): void {
                        $where->where('notifiable_type', PatrolLog::class)
                            ->whereIn('notifiable_id', $scope['patrolIds']);
                    });
                    $applied = true;
                }

                if (
                    $this->hasTableColumn('notification_reads', 'notifiable_type')
                    && $this->hasTableColumn('notification_reads', 'notifiable_id')
                    && $scope['incidentIds']->isNotEmpty()
                ) {
                    $method = $applied ? 'orWhere' : 'where';
                    $query->{$method}(function ($where) use ($scope): void {
                        $where->where('notifiable_type', IncidentReport::class)
                            ->whereIn('notifiable_id', $scope['incidentIds']);
                    });
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    private function auditLogQuery(array $scope)
    {
        if (! Schema::hasTable('audit_logs')) {
            return $this->emptyQuery();
        }

        return DB::table('audit_logs')
            ->where(function ($query) use ($scope): void {
                $applied = false;
                $applied = $this->orWhereAuditSubject($query, $applied, PatrolLog::class, $scope['patrolIds']);
                $applied = $this->orWhereAuditSubject($query, $applied, IncidentReport::class, $scope['incidentIds']);

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    private function orWhereAuditSubject($query, bool $applied, string $subjectType, Collection $ids): bool
    {
        if (
            ! $this->hasTableColumn('audit_logs', 'subject_type')
            || ! $this->hasTableColumn('audit_logs', 'subject_id')
            || $ids->isEmpty()
        ) {
            return $applied;
        }

        $method = $applied ? 'orWhere' : 'where';
        $query->{$method}(function ($where) use ($subjectType, $ids): void {
            $where->where('subject_type', $subjectType)
                ->whereIn('subject_id', $ids);
        });

        return true;
    }

    private function filePaths(array $scope): array
    {
        return collect()
            ->merge($this->pluckPaths('patrol_logs', 'id', $scope['patrolIds'], 'area_selfie_path'))
            ->merge($this->pluckPaths('incident_reports', 'id', $scope['incidentIds'], 'image_path'))
            ->merge($this->pluckPaths('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds'], 'image_path'))
            ->merge($this->pluckPaths('checklist_proof_photos', 'checklist_response_id', $scope['checklistIds'], 'image_path'))
            ->merge($this->pluckPaths('incident_report_images', 'incident_report_id', $scope['incidentIds'], 'image_path'))
            ->merge($this->pluckPaths('incident_evidence', 'id', $scope['incidentEvidenceIds'], 'image_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function checklistProofPhotoCount(array $scope): int
    {
        if (! Schema::hasTable('checklist_proof_photos')) {
            return 0;
        }

        return DB::table('checklist_proof_photos')
            ->where(function ($query) use ($scope): void {
                $applied = false;

                if ($this->hasTableColumn('checklist_proof_photos', 'patrol_log_id') && $scope['patrolIds']->isNotEmpty()) {
                    $query->whereIn('patrol_log_id', $scope['patrolIds']);
                    $applied = true;
                }

                if ($this->hasTableColumn('checklist_proof_photos', 'checklist_response_id') && $scope['checklistIds']->isNotEmpty()) {
                    $method = $applied ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('checklist_response_id', $scope['checklistIds']);
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->count();
    }

    private function deleteChecklistProofPhotos(array $scope): int
    {
        if (! Schema::hasTable('checklist_proof_photos')) {
            return 0;
        }

        return DB::table('checklist_proof_photos')
            ->where(function ($query) use ($scope): void {
                $applied = false;

                if ($this->hasTableColumn('checklist_proof_photos', 'patrol_log_id') && $scope['patrolIds']->isNotEmpty()) {
                    $query->whereIn('patrol_log_id', $scope['patrolIds']);
                    $applied = true;
                }

                if ($this->hasTableColumn('checklist_proof_photos', 'checklist_response_id') && $scope['checklistIds']->isNotEmpty()) {
                    $method = $applied ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('checklist_response_id', $scope['checklistIds']);
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->delete();
    }

    private function countByColumn(string $table, string $column, Collection $ids): int
    {
        if (! $this->hasTableColumn($table, $column) || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function pluckPaths(string $table, string $whereColumn, Collection $ids, string $pathColumn): Collection
    {
        if (! $this->hasTableColumn($table, $whereColumn) || ! Schema::hasColumn($table, $pathColumn) || $ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)
            ->whereIn($whereColumn, $ids)
            ->whereNotNull($pathColumn)
            ->pluck($pathColumn);
    }

    private function deleteByColumn(string $table, string $column, Collection $ids): int
    {
        if (! $this->hasTableColumn($table, $column) || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function deleteByIds(string $table, Collection $ids): int
    {
        if (! $this->hasTableColumn($table, 'id') || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function deleteTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->delete();
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function emptyQuery()
    {
        $table = Schema::hasTable('users') ? 'users' : 'guards';

        return DB::table($table)->whereRaw('1 = 0');
    }
}

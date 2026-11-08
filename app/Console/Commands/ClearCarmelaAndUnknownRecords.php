<?php

namespace App\Console\Commands;

use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClearCarmelaAndUnknownRecords extends Command
{
    protected $signature = 'records:clear-carmela-unknown
        {--force : Delete the targeted records. Without this option, the command only previews counts}';

    protected $description = 'Clear Carmela and unknown-guard patrol activity while keeping accounts, guard profiles, and checkpoints';

    public function handle(): int
    {
        $scope = $this->scope();
        $plan = $this->deletionPlan($scope);

        $this->line('This command targets Carmela Hernandez activity and patrol/incident records with no known guard.');
        $this->line('User accounts, guard profiles, and checkpoints will be kept.');
        $this->line('Matched guard profiles: '.($scope['guardIds']->isEmpty() ? 'none' : $this->guardLabels($scope['guardIds'])));
        $this->newLine();

        $this->table(['Record type', 'Count'], collect($plan['counts'])->map(
            fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count]
        )->values()->all());
        $this->line('Files to delete: '.$plan['file_count']);

        if (! $this->option('force')) {
            $this->warn('Dry run only. No records were deleted.');
            $this->line('Run php artisan records:clear-carmela-unknown --force on the hosted server to delete these records.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(fn (): array => $this->deleteRecords($scope));
        $filePaths = $plan['file_paths'];

        if ($filePaths !== []) {
            Storage::disk('public')->delete($filePaths);
        }

        $this->newLine();
        $this->info('Carmela and unknown-guard activity records cleared.');
        $this->table(['Deleted record type', 'Count'], collect($deleted)->map(
            fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count]
        )->values()->all());
        $this->line('Deleted files: '.count($filePaths));
        $this->line('Kept user accounts, guard profiles, and checkpoints.');

        return self::SUCCESS;
    }

    private function scope(): array
    {
        $guardIds = $this->targetGuardIds();
        $userIds = $this->idsByColumn('guards', 'user_id', $guardIds);
        $patrolIds = $this->targetPatrolIds($guardIds);
        $incidentIds = $this->targetIncidentIds($guardIds, $patrolIds);
        $checklistIds = $this->idsByColumn('checklist_responses', 'patrol_log_id', $patrolIds);
        $incidentEvidenceIds = $this->targetIncidentEvidenceIds($guardIds, $incidentIds);

        return compact('guardIds', 'userIds', 'patrolIds', 'incidentIds', 'checklistIds', 'incidentEvidenceIds');
    }

    private function targetGuardIds(): Collection
    {
        if (! Schema::hasTable('guards')) {
            return collect();
        }

        $query = DB::table('guards')->select('guards.id');

        if (Schema::hasTable('users')) {
            $query->leftJoin('users', 'guards.user_id', '=', 'users.id');
        }

        $query->where(function ($where): void {
            $where->whereRaw('LOWER(guards.name) LIKE ?', ['%carmela%'])
                ->orWhereRaw('LOWER(guards.name) LIKE ?', ['%unknown%']);

            if (Schema::hasTable('users')) {
                $where->orWhereRaw('LOWER(users.name) LIKE ?', ['%carmela%'])
                    ->orWhereRaw('LOWER(users.name) LIKE ?', ['%unknown%']);
            }
        });

        return $query->distinct()->pluck('guards.id');
    }

    private function targetPatrolIds(Collection $guardIds): Collection
    {
        if (! Schema::hasTable('patrol_logs')) {
            return collect();
        }

        return DB::table('patrol_logs')
            ->where(function ($query) use ($guardIds): void {
                if ($guardIds->isNotEmpty()) {
                    $query->whereIn('guard_id', $guardIds)->orWhereNull('guard_id');
                } else {
                    $query->whereNull('guard_id');
                }
            })
            ->pluck('id');
    }

    private function targetIncidentIds(Collection $guardIds, Collection $patrolIds): Collection
    {
        if (! Schema::hasTable('incident_reports')) {
            return collect();
        }

        return DB::table('incident_reports')
            ->where(function ($query) use ($guardIds, $patrolIds): void {
                $applied = false;

                if ($guardIds->isNotEmpty()) {
                    $query->whereIn('guard_id', $guardIds)->orWhereNull('guard_id');
                    $applied = true;
                } else {
                    $query->whereNull('guard_id');
                    $applied = true;
                }

                if ($patrolIds->isNotEmpty()) {
                    $query->orWhereIn('patrol_log_id', $patrolIds);
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->pluck('id');
    }

    private function targetIncidentEvidenceIds(Collection $guardIds, Collection $incidentIds): Collection
    {
        if (! Schema::hasTable('incident_evidence')) {
            return collect();
        }

        return DB::table('incident_evidence')
            ->where(function ($query) use ($guardIds, $incidentIds): void {
                $applied = false;

                if ($guardIds->isNotEmpty()) {
                    $query->whereIn('guard_id', $guardIds);
                    $applied = true;
                }

                if ($incidentIds->isNotEmpty()) {
                    $method = $applied ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('incident_report_id', $incidentIds);
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->pluck('id');
    }

    private function deletionPlan(array $scope): array
    {
        return [
            'counts' => [
                'patrol_logs' => $scope['patrolIds']->count(),
                'checklist_responses' => $scope['checklistIds']->count(),
                'checklist_proof_photos' => $this->countByColumn('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds']),
                'incident_reports' => $scope['incidentIds']->count(),
                'incident_images' => $this->countByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
                'incident_evidence' => $scope['incidentEvidenceIds']->count(),
                'system_notifications' => $this->systemNotificationQuery($scope)->count(),
                'notification_reads' => $this->notificationReadQuery($scope)->count(),
                'audit_logs' => $this->auditLogQuery($scope)->count(),
            ],
            'file_paths' => $this->filePaths($scope),
            'file_count' => count($this->filePaths($scope)),
        ];
    }

    private function deleteRecords(array $scope): array
    {
        return [
            'system_notifications' => $this->systemNotificationQuery($scope)->delete(),
            'notification_reads' => $this->notificationReadQuery($scope)->delete(),
            'audit_logs' => $this->auditLogQuery($scope)->delete(),
            'incident_images' => $this->deleteByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
            'incident_evidence' => $this->deleteByIds('incident_evidence', $scope['incidentEvidenceIds']),
            'incident_reports' => $this->deleteByIds('incident_reports', $scope['incidentIds']),
            'checklist_proof_photos' => $this->deleteByColumn('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds']),
            'checklist_responses' => $this->deleteByIds('checklist_responses', $scope['checklistIds']),
            'patrol_logs' => $this->deleteByIds('patrol_logs', $scope['patrolIds']),
        ];
    }

    private function systemNotificationQuery(array $scope)
    {
        if (! Schema::hasTable('system_notifications') || $scope['userIds']->isEmpty()) {
            return $this->emptyQuery();
        }

        return DB::table('system_notifications')
            ->whereIn('user_id', $scope['userIds'])
            ->whereIn('type', ['Patrol', 'Incident']);
    }

    private function notificationReadQuery(array $scope)
    {
        if (! Schema::hasTable('notification_reads')) {
            return $this->emptyQuery();
        }

        return DB::table('notification_reads')
            ->where(function ($query) use ($scope): void {
                $applied = false;

                if ($scope['userIds']->isNotEmpty()) {
                    $query->whereIn('user_id', $scope['userIds']);
                    $applied = true;
                }

                if ($scope['patrolIds']->isNotEmpty()) {
                    $method = $applied ? 'orWhere' : 'where';
                    $query->{$method}(function ($where) use ($scope): void {
                        $where->where('notifiable_type', PatrolLog::class)
                            ->whereIn('notifiable_id', $scope['patrolIds']);
                    });
                    $applied = true;
                }

                if ($scope['incidentIds']->isNotEmpty()) {
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

                if ($scope['userIds']->isNotEmpty()) {
                    $query->whereIn('user_id', $scope['userIds']);
                    $applied = true;
                }

                foreach ([
                    [PatrolLog::class, $scope['patrolIds']],
                    [IncidentReport::class, $scope['incidentIds']],
                    [Guard::class, $scope['guardIds']],
                    [User::class, $scope['userIds']],
                ] as [$type, $ids]) {
                    if ($ids->isEmpty()) {
                        continue;
                    }

                    $method = $applied ? 'orWhere' : 'where';
                    $query->{$method}(function ($where) use ($type, $ids): void {
                        $where->where('subject_type', $type)->whereIn('subject_id', $ids);
                    });
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    private function filePaths(array $scope): array
    {
        return collect()
            ->merge($this->pluckPaths('patrol_logs', 'id', $scope['patrolIds'], 'area_selfie_path'))
            ->merge($this->pluckPaths('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds'], 'image_path'))
            ->merge($this->pluckPaths('incident_report_images', 'incident_report_id', $scope['incidentIds'], 'image_path'))
            ->merge($this->pluckPaths('incident_evidence', 'id', $scope['incidentEvidenceIds'], 'image_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function guardLabels(Collection $guardIds): string
    {
        return Guard::query()->whereIn('id', $guardIds)->pluck('name')->implode(', ');
    }

    private function idsByColumn(string $table, string $column, Collection $ids): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('user_id');
    }

    private function countByColumn(string $table, string $column, Collection $ids): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function deleteByColumn(string $table, string $column, Collection $ids): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function deleteByIds(string $table, Collection $ids): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function pluckPaths(string $table, string $whereColumn, Collection $ids, string $pathColumn): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $whereColumn) || ! Schema::hasColumn($table, $pathColumn) || $ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)->whereIn($whereColumn, $ids)->whereNotNull($pathColumn)->pluck($pathColumn);
    }

    private function emptyQuery()
    {
        $table = Schema::hasTable('users') ? 'users' : 'guards';

        return DB::table($table)->whereRaw('1 = 0');
    }
}

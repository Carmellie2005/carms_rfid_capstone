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

class ClearCarmelaRecords extends Command
{
    protected $signature = 'guard:clear-carmela-records
        {--guard-id= : Specific guard id to clean when more than one Carmela match exists}
        {--force : Delete the records. Without this option, the command only previews counts}';

    protected $description = 'Clear Carmela patrol, incident, notification, and audit records while keeping her account and guard profile';

    public function handle(): int
    {
        $guards = $this->matchingGuards();

        if ($guards->isEmpty()) {
            $this->error('No Carmela guard profile was found.');

            return self::FAILURE;
        }

        if ($guards->count() > 1) {
            $this->error('More than one matching Carmela guard profile was found. Rerun with --guard-id=ID.');

            $guards->each(function (Guard $guard): void {
                $this->line(sprintf(
                    '#%s | %s | %s | %s',
                    $guard->id,
                    $guard->employee_no,
                    $guard->name,
                    $guard->email ?? 'no email'
                ));
            });

            return self::FAILURE;
        }

        $guard = $guards->first();
        $scope = $this->scopeFor($guard);
        $plan = $this->deletionPlan($guard, $scope);

        $this->line('Matched guard profile:');
        $this->line(sprintf(
            '#%s %s | %s | user #%s %s',
            $guard->id,
            $guard->employee_no,
            $guard->name,
            $guard->user_id ?? 'none',
            $guard->user?->name ?? 'no linked user'
        ));
        $this->newLine();

        $this->table(['Record type', 'Count'], collect($plan['counts'])->map(
            fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count]
        )->values()->all());

        $this->line('Files to delete: '.$plan['file_count']);
        $this->line('User accounts kept: '.$plan['kept_users']);
        $this->line('Guard profiles kept: '.$plan['kept_guards']);

        if (! $this->option('force')) {
            $this->warn('Dry run only. No records were deleted.');
            $this->line('Run php artisan guard:clear-carmela-records --force in the production shell to delete these records.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(fn (): array => $this->deleteRecords($guard, $scope));

        $filePaths = $plan['file_paths'];

        if ($filePaths !== []) {
            Storage::disk('public')->delete($filePaths);
        }

        $this->newLine();
        $this->info('Carmela activity records cleared.');
        $this->table(['Deleted record type', 'Count'], collect($deleted)->map(
            fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count]
        )->values()->all());
        $this->line('Deleted files: '.count($filePaths));
        $this->line('Kept Carmela user account and guard profile.');

        return self::SUCCESS;
    }

    private function matchingGuards(): Collection
    {
        if (! Schema::hasTable('guards')) {
            return collect();
        }

        if ($this->option('guard-id')) {
            return Guard::with('user')->whereKey($this->option('guard-id'))->get();
        }

        $query = DB::table('guards')
            ->leftJoin('users', 'guards.user_id', '=', 'users.id')
            ->select('guards.id')
            ->distinct();

        $conditions = collect([
            ['guards', 'employee_no', 'TEST-01'],
            ['guards', 'name', 'Carmela Bihay Hernandez'],
            ['guards', 'email', 'carmela.bihay.hernandez@guard.local'],
            ['guards', 'rfid_uid', 'F33C8D37'],
            ['users', 'name', 'Carmela Bihay Hernandez'],
            ['users', 'email', 'carmela.bihay.hernandez@guard.local'],
            ['users', 'username', 'carmela.bihay.hernandez'],
        ])->filter(fn (array $condition): bool => Schema::hasColumn($condition[0], $condition[1]))
            ->values();

        if ($conditions->isEmpty()) {
            return collect();
        }

        $query->where(function ($where) use ($conditions): void {
            foreach ($conditions as $index => [$table, $column, $value]) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $where->{$method}($table.'.'.$column, $value);
            }
        });

        $guardIds = $query->pluck('guards.id');

        return Guard::with('user')->whereIn('id', $guardIds)->get();
    }

    private function scopeFor(Guard $guard): array
    {
        $guardIds = collect([$guard->id]);
        $userIds = collect([$guard->user_id])->filter()->values();
        $patrolIds = $this->idsByColumn('patrol_logs', 'guard_id', $guardIds);
        $incidentIds = $this->incidentIds($guardIds, $patrolIds);
        $checklistIds = $this->idsByColumn('checklist_responses', 'patrol_log_id', $patrolIds);

        return compact('guardIds', 'userIds', 'patrolIds', 'incidentIds', 'checklistIds');
    }

    private function deletionPlan(Guard $guard, array $scope): array
    {
        $counts = [
            'patrol_logs' => $scope['patrolIds']->count(),
            'checklist_responses' => $scope['checklistIds']->count(),
            'checklist_proof_photos' => $this->countByColumn('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds']),
            'incident_reports' => $scope['incidentIds']->count(),
            'incident_images' => $this->countByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
            'notification_reads' => $this->notificationReadQuery($scope)->count(),
            'audit_logs' => $this->auditLogQuery($guard, $scope)->count(),
        ];

        $filePaths = $this->filePaths($scope);

        return [
            'counts' => $counts,
            'file_paths' => $filePaths,
            'file_count' => count($filePaths),
            'kept_users' => $scope['userIds']->count(),
            'kept_guards' => $scope['guardIds']->count(),
        ];
    }

    private function deleteRecords(Guard $guard, array $scope): array
    {
        return [
            'notification_reads' => $this->notificationReadQuery($scope)->delete(),
            'audit_logs' => $this->auditLogQuery($guard, $scope)->delete(),
            'incident_images' => $this->deleteByColumn('incident_report_images', 'incident_report_id', $scope['incidentIds']),
            'incident_reports' => $this->deleteByIds('incident_reports', $scope['incidentIds']),
            'checklist_proof_photos' => $this->deleteByColumn('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds']),
            'checklist_responses' => $this->deleteByIds('checklist_responses', $scope['checklistIds']),
            'patrol_logs' => $this->deleteByIds('patrol_logs', $scope['patrolIds']),
        ];
    }

    private function idsByColumn(string $table, string $column, Collection $ids): Collection
    {
        if (! $this->hasTableColumn($table, $column) || $ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('id');
    }

    private function incidentIds(Collection $guardIds, Collection $patrolIds): Collection
    {
        if (! Schema::hasTable('incident_reports')) {
            return collect();
        }

        return DB::table('incident_reports')
            ->where(function ($query) use ($guardIds, $patrolIds): void {
                $applied = false;

                if (Schema::hasColumn('incident_reports', 'guard_id') && $guardIds->isNotEmpty()) {
                    $query->whereIn('guard_id', $guardIds);
                    $applied = true;
                }

                if (Schema::hasColumn('incident_reports', 'patrol_log_id') && $patrolIds->isNotEmpty()) {
                    $method = $applied ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('patrol_log_id', $patrolIds);
                    $applied = true;
                }

                if (! $applied) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->pluck('id');
    }

    private function countByColumn(string $table, string $column, Collection $ids): int
    {
        if (! $this->hasTableColumn($table, $column) || $ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function notificationReadQuery(array $scope)
    {
        if (! Schema::hasTable('notification_reads')) {
            return $this->emptyQuery();
        }

        return DB::table('notification_reads')
            ->where(function ($query) use ($scope): void {
                $applied = false;

                if (Schema::hasColumn('notification_reads', 'user_id') && $scope['userIds']->isNotEmpty()) {
                    $query->whereIn('user_id', $scope['userIds']);
                    $applied = true;
                }

                if (
                    Schema::hasColumn('notification_reads', 'notifiable_type')
                    && Schema::hasColumn('notification_reads', 'notifiable_id')
                    && $scope['patrolIds']->isNotEmpty()
                ) {
                    $method = $applied ? 'orWhere' : 'where';
                    $query->{$method}(function ($where) use ($scope): void {
                        $where->where('notifiable_type', PatrolLog::class)
                            ->whereIn('notifiable_id', $scope['patrolIds']);
                    });
                    $applied = true;
                }

                if (
                    Schema::hasColumn('notification_reads', 'notifiable_type')
                    && Schema::hasColumn('notification_reads', 'notifiable_id')
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

    private function auditLogQuery(Guard $guard, array $scope)
    {
        if (! Schema::hasTable('audit_logs')) {
            return $this->emptyQuery();
        }

        return DB::table('audit_logs')
            ->where(function ($query) use ($guard, $scope): void {
                $applied = false;

                if (Schema::hasColumn('audit_logs', 'user_id') && $scope['userIds']->isNotEmpty()) {
                    $query->whereIn('user_id', $scope['userIds']);
                    $applied = true;
                }

                if (Schema::hasColumn('audit_logs', 'actor_name')) {
                    $method = $applied ? 'orWhere' : 'where';
                    $query->{$method}('actor_name', $guard->name);
                    $applied = true;
                }

                $applied = $this->orWhereAuditSubject($query, $applied, User::class, $scope['userIds']);
                $applied = $this->orWhereAuditSubject($query, $applied, Guard::class, $scope['guardIds']);
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
            ! Schema::hasColumn('audit_logs', 'subject_type')
            || ! Schema::hasColumn('audit_logs', 'subject_id')
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
            ->merge($this->pluckPaths('checklist_proof_photos', 'patrol_log_id', $scope['patrolIds'], 'image_path'))
            ->merge($this->pluckPaths('incident_report_images', 'incident_report_id', $scope['incidentIds'], 'image_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

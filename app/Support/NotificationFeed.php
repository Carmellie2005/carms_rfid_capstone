<?php

namespace App\Support;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationFeed
{
    public const DROPDOWN_LIMIT = 5;
    public const PAGE_SIZE = 12;

    private const INCIDENT_STATUSES = ['submitted', 'under_review'];
    private const PATROL_STATUSES = ['suspicious', 'invalid', 'pending_face', 'profile_incomplete', 'outside_schedule'];

    public static function unreadCountFor(User $user): int
    {
        return static::incidentQuery($user, true)->count()
            + static::patrolQuery($user, true)->count();
    }

    public static function unreadItemsFor(User $user, int $limit = self::DROPDOWN_LIMIT): Collection
    {
        return static::itemsFor($user, true)->take($limit)->values();
    }

    public static function paginateFor(User $user, int $perPage = self::PAGE_SIZE, ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: LengthAwarePaginator::resolveCurrentPage();
        $items = static::itemsFor($user, false);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public static function unreadSubjectsFor(User $user): array
    {
        return [
            ...static::incidentQuery($user, true)->get()->all(),
            ...static::patrolQuery($user, true)->get()->all(),
        ];
    }

    public static function authorizedSubject(User $user, string $type, int $id): Model
    {
        if ($type === 'incident') {
            return static::incidentQuery($user, false)
                ->whereKey($id)
                ->firstOrFail();
        }

        return static::patrolQuery($user, false)
            ->whereKey($id)
            ->firstOrFail();
    }

    private static function itemsFor(User $user, bool $unreadOnly): Collection
    {
        $incidents = static::incidentQuery($user, $unreadOnly, true)
            ->get()
            ->map(fn (IncidentReport $incident) => static::incidentItem($incident, $user));

        $patrols = static::patrolQuery($user, $unreadOnly, true)
            ->get()
            ->map(fn (PatrolLog $patrol) => static::patrolItem($patrol, $user));

        return $incidents
            ->concat($patrols)
            ->sortByDesc(fn (array $item) => $item['sort_timestamp'])
            ->values();
    }

    private static function incidentQuery(User $user, bool $unreadOnly, bool $withNotificationReads = false): Builder
    {
        $query = IncidentReport::query()
            ->with(['securityGuard', 'checkpoint'])
            ->whereIn('status', self::INCIDENT_STATUSES)
            ->when(
                $user->role !== 'admin',
                fn (Builder $query) => $query->where('guard_id', $user->guardProfile?->id ?? 0)
            );

        if ($withNotificationReads) {
            $query->with(['notificationReads' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        return $unreadOnly ? static::unreadOnly($query, $user) : $query;
    }

    private static function patrolQuery(User $user, bool $unreadOnly, bool $withNotificationReads = false): Builder
    {
        $todayDate = now('Asia/Manila')->toDateString();
        $query = PatrolLog::query()
            ->with(['securityGuard', 'checkpoint'])
            ->whereIn('status', self::PATROL_STATUSES)
            ->whereDate('scanned_at', $todayDate)
            ->when(
                $user->role !== 'admin',
                fn (Builder $query) => $query->where('guard_id', $user->guardProfile?->id ?? 0)
            );

        if ($withNotificationReads) {
            $query->with(['notificationReads' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        return $unreadOnly ? static::unreadOnly($query, $user) : $query;
    }

    private static function unreadOnly(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('notificationReads', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    private static function incidentItem(IncidentReport $incident, User $user): array
    {
        $readAt = $incident->notificationReads->first()?->read_at;
        $time = $incident->incident_at;

        return [
            'read_type' => 'incident',
            'read_id' => $incident->id,
            'type' => 'Incident',
            'title' => $incident->category ?: 'Incident report',
            'body' => collect([$incident->securityGuard?->name, $incident->checkpoint?->name])->filter()->implode(' - ') ?: 'Incident report submitted',
            'time_label' => static::timeLabel($time),
            'relative_time' => $time?->diffForHumans(),
            'sort_timestamp' => $time?->timestamp ?? 0,
            'badge' => ucfirst($incident->priority ?: 'Normal'),
            'href' => $user->role === 'admin'
                ? route('incidents.index', ['status' => $incident->status])
                : route('patrol-logs.index', ['date' => $incident->incident_at?->toDateString()]),
            'is_read' => (bool) $readAt,
            'read_at_label' => static::timeLabel($readAt),
        ];
    }

    private static function patrolItem(PatrolLog $patrol, User $user): array
    {
        $readAt = $patrol->notificationReads->first()?->read_at;
        $time = $patrol->scanned_at;
        $statusLabel = Str::of($patrol->status)->replace('_', ' ')->title()->toString();

        return [
            'read_type' => 'patrol',
            'read_id' => $patrol->id,
            'type' => 'Patrol',
            'title' => $statusLabel.' scan',
            'body' => collect([$patrol->securityGuard?->name, $patrol->checkpoint?->name])->filter()->implode(' - ') ?: 'Checkpoint scan needs review',
            'time_label' => static::timeLabel($time),
            'relative_time' => $time?->diffForHumans(),
            'sort_timestamp' => $time?->timestamp ?? 0,
            'badge' => $statusLabel,
            'href' => $user->role === 'admin'
                ? route('scan-issues.index', ['status' => $patrol->status])
                : route('patrol-logs.index', ['status' => $patrol->status, 'date' => $patrol->scanned_at?->toDateString()]),
            'is_read' => (bool) $readAt,
            'read_at_label' => static::timeLabel($readAt),
        ];
    }

    private static function timeLabel($time): string
    {
        return $time
            ? $time->copy()->timezone(config('app.timezone'))->format('M d, Y h:i A')
            : 'Not recorded';
    }
}

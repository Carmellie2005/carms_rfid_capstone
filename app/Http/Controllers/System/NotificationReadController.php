<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\NotificationRead;
use App\Models\PatrolLog;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationReadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['incident', 'patrol'])],
            'id' => ['required', 'integer'],
        ]);

        $subject = $this->authorizedSubject($request, $data['type'], (int) $data['id']);

        NotificationRead::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'notifiable_type' => get_class($subject),
                'notifiable_id' => $subject->getKey(),
            ],
            ['read_at' => now()]
        );

        AuditLogger::record('notification_marked_read', 'Notification marked as read.', $subject, [
            'notification_type' => $data['type'],
            'notification_id' => $subject->getKey(),
        ]);

        return back()->with('status', 'Notification marked as read.');
    }

    public function storeAll(Request $request): RedirectResponse
    {
        $now = now();
        $marked = 0;

        foreach ($this->unreadSubjectsFor($request) as $subject) {
            NotificationRead::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'notifiable_type' => get_class($subject),
                    'notifiable_id' => $subject->getKey(),
                ],
                ['read_at' => $now]
            );

            $marked++;
        }

        AuditLogger::record('notifications_marked_read', 'All current notifications marked as read.', null, [
            'marked_count' => $marked,
        ]);

        return back()->with('status', $marked === 1 ? 'Notification marked as read.' : "{$marked} notifications marked as read.");
    }

    private function authorizedSubject(Request $request, string $type, int $id): Model
    {
        if ($type === 'incident') {
            return $this->authorizedIncidentQuery($request, false)
                ->whereKey($id)
                ->firstOrFail();
        }

        return $this->authorizedPatrolQuery($request, false)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function unreadSubjectsFor(Request $request): array
    {
        return [
            ...$this->authorizedIncidentQuery($request, true)->get()->all(),
            ...$this->authorizedPatrolQuery($request, true)->get()->all(),
        ];
    }

    private function authorizedIncidentQuery(Request $request, bool $unreadOnly)
    {
        $query = IncidentReport::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->when(
                $request->user()->role !== 'admin',
                fn ($query) => $query->where('guard_id', $request->user()->guardProfile?->id ?? 0)
            );

        return $unreadOnly
            ? $query->whereDoesntHave('notificationReads', fn ($query) => $query->where('user_id', $request->user()->id))
            : $query;
    }

    private function authorizedPatrolQuery(Request $request, bool $unreadOnly)
    {
        $todayDate = now('Asia/Manila')->toDateString();
        $query = PatrolLog::query()
            ->whereIn('status', ['suspicious', 'invalid', 'pending_face', 'profile_incomplete', 'outside_schedule'])
            ->whereDate('scanned_at', $todayDate)
            ->when(
                $request->user()->role !== 'admin',
                fn ($query) => $query->where('guard_id', $request->user()->guardProfile?->id ?? 0)
            );

        return $unreadOnly
            ? $query->whereDoesntHave('notificationReads', fn ($query) => $query->where('user_id', $request->user()->id))
            : $query;
    }
}

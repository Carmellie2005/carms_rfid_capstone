<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\NotificationRead;
use App\Support\AuditLogger;
use App\Support\NotificationFeed;
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

        $subject = NotificationFeed::authorizedSubject($request->user(), $data['type'], (int) $data['id']);

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

        foreach (NotificationFeed::unreadSubjectsFor($request->user()) as $subject) {
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
}

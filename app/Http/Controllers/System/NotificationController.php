<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\NotificationFeed;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = [
            'notifications' => NotificationFeed::paginateFor($request->user())->withQueryString(),
            'unreadCount' => NotificationFeed::unreadCountFor($request->user()),
        ];

        if ($request->boolean('embedded')) {
            return view('system.notifications.embedded', $data);
        }

        return view('system.notifications.index', $data);
    }
}

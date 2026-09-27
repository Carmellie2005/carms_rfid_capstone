<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function config(WebPushNotifier $notifier): JsonResponse
    {
        $settings = [
            'WEBPUSH_VAPID_SUBJECT' => config('services.webpush.vapid_subject'),
            'WEBPUSH_VAPID_PUBLIC_KEY' => config('services.webpush.vapid_public_key'),
            'WEBPUSH_VAPID_PRIVATE_KEY' => config('services.webpush.vapid_private_key'),
        ];

        return response()->json([
            'enabled' => $notifier->isConfigured(),
            'public_key' => $settings['WEBPUSH_VAPID_PUBLIC_KEY'],
            'missing' => array_keys(array_filter($settings, fn ($value) => blank($value))),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:500'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:20'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?: 'aes128gcm',
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Web push alerts enabled for this device.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json([
            'message' => 'Web push alerts disabled for this device.',
        ]);
    }
}

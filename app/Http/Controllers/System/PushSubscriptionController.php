<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function config(WebPushNotifier $webPush): JsonResponse
    {
        return response()->json([
            'enabled' => $webPush->enabled(),
            'publicKey' => $webPush->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:2048'],
            'keys.auth' => ['required', 'string', 'max:2048'],
            'contentEncoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => str($request->userAgent() ?: 'Unknown browser')->limit(255)->toString(),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $request->user()
            ->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }
}

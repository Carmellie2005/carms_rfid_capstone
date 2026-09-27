<?php

namespace App\Services;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use App\Support\NotificationFeed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.vapid_public_key'))
            && filled(config('services.webpush.vapid_private_key'))
            && filled(config('services.webpush.vapid_subject'));
    }

    public function sendIncidentSubmitted(IncidentReport $incident): int
    {
        if (! in_array($incident->status, NotificationFeed::SUPERVISOR_INCIDENT_STATUSES, true)) {
            return 0;
        }

        $incident->loadMissing(['securityGuard', 'checkpoint']);
        $category = $incident->category ?: 'Incident report';
        $guard = $incident->securityGuard?->name ?: 'A guard';
        $checkpoint = $incident->checkpoint?->name ?: $incident->location;

        return $this->sendToSupervisors([
            'title' => 'New incident report',
            'body' => collect([$guard, $category, $checkpoint])->filter()->implode(' - '),
            'url' => route('incidents.index', ['status' => $incident->status]),
            'tag' => 'incident-'.$incident->id,
            'type' => 'incident',
        ]);
    }

    public function sendPatrolScanIssue(PatrolLog $patrolLog): int
    {
        if (! in_array($patrolLog->status, NotificationFeed::SUPERVISOR_PATROL_STATUSES, true)) {
            return 0;
        }

        $patrolLog->loadMissing(['securityGuard', 'checkpoint']);
        $statusLabel = Str::of($patrolLog->status)->replace('_', ' ')->title()->toString();
        $guard = $patrolLog->securityGuard?->name ?: 'Unknown RFID';
        $checkpoint = $patrolLog->checkpoint?->name ?: $patrolLog->checkpoint_code;

        return $this->sendToSupervisors([
            'title' => $statusLabel.' scan',
            'body' => collect([$guard, $checkpoint])->filter()->implode(' - ') ?: 'Checkpoint scan needs review.',
            'url' => route('scan-issues.index', ['status' => $patrolLog->status]),
            'tag' => 'patrol-'.$patrolLog->id,
            'type' => 'patrol',
        ]);
    }

    public function sendToSupervisors(array $payload): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $subscriptions = StoredPushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'admin'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    private function sendToSubscriptions($subscriptions, array $payload): int
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.vapid_subject'),
                'publicKey' => config('services.webpush.vapid_public_key'),
                'privateKey' => config('services.webpush.vapid_private_key'),
            ],
        ], [
            'TTL' => (int) config('services.webpush.ttl', 3600),
            'urgency' => 'high',
        ], 10);
        $webPush->setReuseVAPIDHeaders(true);

        $byEndpoint = [];
        $queued = 0;
        $payloadJson = json_encode($this->payload($payload), JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]), $payloadJson);

                $byEndpoint[$subscription->endpoint] = $subscription;
                $queued++;
            } catch (\Throwable $error) {
                Log::warning('Unable to queue web push notification.', [
                    'subscription_id' => $subscription->id,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        if ($queued === 0) {
            return 0;
        }

        foreach ($webPush->flush() as $report) {
            $subscription = $byEndpoint[$report->getEndpoint()] ?? null;

            if (! $subscription) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
                continue;
            }

            if ($report->isSuccess()) {
                $subscription->forceFill(['last_used_at' => now()])->save();
            } else {
                Log::warning('Web push notification failed.', [
                    'subscription_id' => $subscription->id,
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $queued;
    }

    private function payload(array $payload): array
    {
        return [
            'title' => $payload['title'] ?? 'SLSU Bontoc Patrol',
            'body' => $payload['body'] ?? 'New patrol alert received.',
            'url' => $payload['url'] ?? route('dashboard'),
            'tag' => $payload['tag'] ?? 'slsu-bontoc-patrol',
            'type' => $payload['type'] ?? 'alert',
            'icon' => asset('pwa-icon-192.png'),
            'badge' => asset('pwa-icon-maskable-192.png'),
        ];
    }
}

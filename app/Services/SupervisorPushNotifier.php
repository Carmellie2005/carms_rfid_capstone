<?php

namespace App\Services;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class SupervisorPushNotifier
{
    public function sendIncidentSubmitted(IncidentReport $incidentReport): void
    {
        $incidentReport->loadMissing(['securityGuard', 'checkpoint', 'patrolLog']);

        $category = $incidentReport->category ?: 'Incident';
        $guard = $incidentReport->securityGuard?->name ?: 'a security guard';
        $checkpoint = $incidentReport->checkpoint?->name
            ?: $incidentReport->location
            ?: 'a checkpoint';

        $this->sendToSupervisors([
            'title' => 'New incident report',
            'body' => "{$category} reported by {$guard} at {$checkpoint}.",
            'url' => route('incidents.index', ['status' => $incidentReport->status]),
            'tag' => 'incident-'.$incidentReport->id,
        ]);
    }

    public function sendPatrolSubmitted(PatrolLog $patrolLog): void
    {
        $patrolLog->loadMissing(['securityGuard', 'checkpoint']);

        $guard = $patrolLog->securityGuard?->name ?: 'A security guard';
        $checkpoint = $patrolLog->checkpoint?->name
            ?: $patrolLog->checkpoint_code
            ?: 'a checkpoint';

        $this->sendToSupervisors([
            'title' => 'New patrol submitted',
            'body' => "{$guard} completed a patrol at {$checkpoint}.",
            'url' => route('patrol-logs.index', ['date' => $patrolLog->scanned_at?->toDateString()]),
            'tag' => 'patrol-submitted-'.$patrolLog->id,
        ]);
    }

    private function sendToSupervisors(array $payload): void
    {
        $vapid = $this->vapidConfig();

        if (! $vapid) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'admin'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $ttl = max(60, (int) config('webpush.ttl', 3600));
        $payload = [
            'icon' => asset('pwa-icon-192.png'),
            'badge' => asset('notification-badge.png'),
            ...$payload,
        ];

        try {
            $webPush = new WebPush(['VAPID' => $vapid], ['TTL' => $ttl, 'urgency' => 'high'], 15);
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            Log::warning('Supervisor web push could not be prepared.', [
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    $encodedPayload,
                    ['TTL' => $ttl],
                );

                if ($report->isSuccess()) {
                    $subscription->forceFill(['last_used_at' => now()])->save();

                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();

                    continue;
                }

                Log::warning('Supervisor web push was rejected.', [
                    'endpoint_hash' => $subscription->endpoint_hash,
                    'reason' => $report->getReason(),
                ]);
            } catch (Throwable $exception) {
                Log::warning('Supervisor web push could not be sent.', [
                    'endpoint_hash' => $subscription->endpoint_hash,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function vapidConfig(): ?array
    {
        $subject = config('webpush.vapid.subject') ?: config('app.url');
        $publicKey = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');

        if (! filled($subject) || ! filled($publicKey) || ! filled($privateKey)) {
            return null;
        }

        return [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ];
    }
}

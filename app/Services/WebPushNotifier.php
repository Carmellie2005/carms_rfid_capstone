<?php

namespace App\Services;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\NotificationFeed;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushNotifier
{
    public function enabled(): bool
    {
        return filled($this->publicKey())
            && filled($this->privateKey())
            && filled($this->subject());
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.vapid.public_key');
    }

    public function sendSupervisorIncidentAlert(IncidentReport $incident): void
    {
        if (! in_array($incident->status, NotificationFeed::SUPERVISOR_INCIDENT_STATUSES, true)) {
            return;
        }

        $this->sendToSupervisors([
            'title' => 'SLSU Bontoc Patrol',
            'body' => 'An incident report needs supervisor review.',
            'url' => route('incidents.index', ['status' => $incident->status]),
            'tag' => 'incident-'.$incident->id,
        ]);
    }

    public function sendSupervisorPatrolAlert(PatrolLog $patrolLog): void
    {
        if (! in_array($patrolLog->status, NotificationFeed::SUPERVISOR_PATROL_STATUSES, true)) {
            return;
        }

        $this->sendToSupervisors([
            'title' => 'SLSU Bontoc Patrol',
            'body' => 'A checkpoint scan needs supervisor review.',
            'url' => route('scan-issues.index', ['status' => $patrolLog->status]),
            'tag' => 'patrol-'.$patrolLog->id,
        ]);
    }

    private function sendToSupervisors(array $payload): void
    {
        if (! $this->enabled()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'admin'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->subject(),
                    'publicKey' => $this->publicKey(),
                    'privateKey' => $this->privateKey(),
                ],
            ], [
                'TTL' => (int) config('services.webpush.ttl', 3600),
                'urgency' => 'high',
            ]);

            $jsonPayload = json_encode([
                'title' => $payload['title'],
                'body' => $payload['body'],
                'url' => $payload['url'],
                'tag' => $payload['tag'],
            ], JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    $jsonPayload
                );
            }

            foreach ($webPush->flush() as $report) {
                $subscription = $subscriptions->first(
                    fn (PushSubscription $subscription) => $subscription->endpoint === $report->getEndpoint()
                );

                if (! $subscription) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                    continue;
                }

                Log::warning('Web push notification failed.', [
                    'subscription_id' => $subscription->id,
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Web push notification could not be sent.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function privateKey(): ?string
    {
        return config('services.webpush.vapid.private_key');
    }

    private function subject(): ?string
    {
        return config('services.webpush.vapid.subject') ?: config('app.url');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class TestWebPushNotification extends Command
{
    protected $signature = 'webpush:test {--user= : Supervisor user ID to test}';

    protected $description = 'Send a test browser push notification to supervisor subscriptions.';

    public function handle(): int
    {
        $vapid = $this->vapidConfig();

        if (! $vapid) {
            $this->error('Missing WEBPUSH_VAPID_PUBLIC_KEY or WEBPUSH_VAPID_PRIVATE_KEY.');

            return self::FAILURE;
        }

        $subscriptions = PushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'admin'))
            ->when($this->option('user'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No supervisor push subscriptions found. Log in as supervisor, open the bell, and click Enable Push first.');

            return self::SUCCESS;
        }

        $ttl = max(60, (int) config('webpush.ttl', 3600));
        $payload = json_encode([
            'title' => 'SLSU Bontoc Patrol test',
            'body' => 'Browser push notifications are working.',
            'url' => route('notifications.index'),
            'tag' => 'webpush-test',
            'icon' => asset('pwa-icon-192.png'),
            'badge' => asset('pwa-icon-maskable-192.png'),
        ], JSON_THROW_ON_ERROR);

        $sent = 0;
        $failed = 0;

        try {
            $webPush = new WebPush(['VAPID' => $vapid], ['TTL' => $ttl, 'urgency' => 'high'], 15);
        } catch (Throwable $exception) {
            $this->error('Could not prepare web push: '.$exception->getMessage());

            return self::FAILURE;
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
                    $payload,
                    ['TTL' => $ttl],
                );

                if ($report->isSuccess()) {
                    $sent++;
                    $subscription->forceFill(['last_used_at' => now()])->save();

                    continue;
                }

                $failed++;

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                }

                $this->warn("Failed endpoint {$subscription->endpoint_hash}: {$report->getReason()}");
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('Test web push could not be sent.', [
                    'endpoint_hash' => $subscription->endpoint_hash,
                    'error' => $exception->getMessage(),
                ]);

                $this->warn("Failed endpoint {$subscription->endpoint_hash}: {$exception->getMessage()}");
            }
        }

        $this->info("Web push test complete. Sent: {$sent}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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

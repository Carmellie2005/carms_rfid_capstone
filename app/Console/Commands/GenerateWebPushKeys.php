<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateWebPushKeys extends Command
{
    protected $signature = 'webpush:keys';

    protected $description = 'Generate VAPID keys for browser push notifications.';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            $this->error('Could not generate VAPID keys: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Copy these values to your .env or Dokploy environment variables:');
        $this->newLine();
        $this->line('WEBPUSH_VAPID_SUBJECT='.config('webpush.vapid.subject', config('app.url')));
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}

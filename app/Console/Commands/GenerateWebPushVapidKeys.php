<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for browser web push notifications';

    public function handle(): int
    {
        $keys = $this->generateKeys();

        $this->line('Add these environment variables in Dokploy:');
        $this->newLine();
        $this->line('WEBPUSH_VAPID_SUBJECT=mailto:'.config('mail.from.address'));
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }

    private function generateKeys(): array
    {
        try {
            return VAPID::createVapidKeys();
        } catch (\Throwable $firstError) {
            foreach ($this->opensslConfigCandidates() as $configPath) {
                try {
                    return $this->generateKeysWithOpenSsl($configPath);
                } catch (\Throwable) {
                    //
                }
            }

            throw $firstError;
        }
    }

    private function generateKeysWithOpenSsl(?string $configPath): array
    {
        $options = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        if ($configPath) {
            $options['config'] = $configPath;
        }

        $key = openssl_pkey_new($options);

        if ($key === false) {
            throw new \RuntimeException('Unable to create VAPID key pair.');
        }

        $details = openssl_pkey_get_details($key);
        $ec = is_array($details) ? ($details['ec'] ?? null) : null;

        if (! is_array($ec) || ! isset($ec['x'], $ec['y'], $ec['d'])) {
            throw new \RuntimeException('Unable to read VAPID key pair details.');
        }

        $publicKey = "\x04"
            .str_pad((string) $ec['x'], 32, "\0", STR_PAD_LEFT)
            .str_pad((string) $ec['y'], 32, "\0", STR_PAD_LEFT);
        $privateKey = str_pad((string) $ec['d'], 32, "\0", STR_PAD_LEFT);

        return [
            'publicKey' => $this->base64Url($publicKey),
            'privateKey' => $this->base64Url($privateKey),
        ];
    }

    private function opensslConfigCandidates(): array
    {
        $phpDirectory = dirname(PHP_BINARY);

        return collect([
            getenv('OPENSSL_CONF') ?: null,
            $phpDirectory.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            base_path('openssl.cnf'),
            'C:/laragon/bin/git/usr/ssl/openssl.cnf',
        ])
            ->filter(fn ($path) => $path && is_file($path))
            ->prepend(null)
            ->unique()
            ->values()
            ->all();
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

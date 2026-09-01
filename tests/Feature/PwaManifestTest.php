<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    public function test_manifest_uses_slsu_patrol_install_assets(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame('SLSU Patrol', $manifest['name']);
        $this->assertSame('SLSU Patrol', $manifest['short_name']);

        $icons = collect($manifest['icons']);

        $this->assertTrue($icons->contains(fn ($icon) => $icon['src'] === '/pwa-icon-192.png?v=slsu-logo-v10' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn ($icon) => $icon['src'] === '/pwa-icon-512.png?v=slsu-logo-v10' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn ($icon) => $icon['src'] === '/pwa-icon-maskable-192.png?v=slsu-logo-v10' && $icon['purpose'] === 'maskable'));
        $this->assertTrue($icons->contains(fn ($icon) => $icon['src'] === '/pwa-icon-maskable-512.png?v=slsu-logo-v10' && $icon['purpose'] === 'maskable'));

        $this->assertFileExists(public_path('pwa-icon-192.png'));
        $this->assertFileExists(public_path('pwa-icon-512.png'));
        $this->assertFileExists(public_path('pwa-icon-maskable-192.png'));
        $this->assertFileExists(public_path('pwa-icon-maskable-512.png'));
    }
}

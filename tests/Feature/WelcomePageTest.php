<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_public_landing_page_uses_compact_mobile_cards(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('min-h-[calc(100svh-128px)]', false)
            ->assertSee('grid grid-cols-2 gap-2 sm:mt-8 sm:gap-4 md:grid-cols-2 xl:grid-cols-4', false)
            ->assertSee('rounded-md border border-emerald-100 bg-emerald-50/60 p-3', false)
            ->assertSee('text-xs leading-5 text-slate-600', false);
    }

    public function test_public_landing_page_uses_original_text_footer(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertDontSee('Development Team')
            ->assertDontSee('images/developers/')
            ->assertSee('Carmela B. Hernandez')
            ->assertSee('Lead Programmer')
            ->assertSee('Cherry Ann R. Himo')
            ->assertSee('System Analyst')
            ->assertSee('Clarice R. Gumapi')
            ->assertSee('Documentation Specialist')
            ->assertSee('Karyl G. Viure')
            ->assertSee('Quality Assurance');
    }

    public function test_public_landing_page_has_pwa_install_success_modal(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee("pwaInstallPrompt({ appName: 'BC Patrol'", false)
            ->assertSee('installModalOpen', false)
            ->assertSee('installModalTitle()', false)
            ->assertSee('Open BC Patrol');
    }
}

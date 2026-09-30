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

    public function test_public_landing_page_shows_development_team_photos(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Development Team')
            ->assertSee('images/developers/carmela.jpg')
            ->assertSee('images/developers/cherry.jpg')
            ->assertSee('images/developers/clarice.jpg')
            ->assertSee('images/developers/karyl.jpg')
            ->assertSee('bg-sky-900 text-white', false)
            ->assertSee('items-start justify-start', false)
            ->assertSee('Carmela B. Hernandez')
            ->assertSee('Lead Programmer')
            ->assertSee('Cherry Ann R. Himo')
            ->assertSee('Documentation Specialist')
            ->assertSee('Clarice R. Gumapi')
            ->assertSee('System Analyst')
            ->assertSee('Karyl G. Viure')
            ->assertSee('Quality Assurance');
    }

    public function test_public_landing_page_has_pwa_install_success_modal(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee("pwaInstallPrompt({ appName: 'SLSU BC Patrol'", false)
            ->assertSee('installModalOpen', false)
            ->assertSee('installModalTitle()', false)
            ->assertSee('Access System');
    }
}

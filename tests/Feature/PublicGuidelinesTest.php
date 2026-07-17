<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGuidelinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_guidelines_can_be_opened_before_login(): void
    {
        $this->get(route('help.staff-guidelines'))
            ->assertOk()
            ->assertSee('Staff Guidelines')
            ->assertSee('Request Access')
            ->assertSee('After Login')
            ->assertDontSee('Your Matters Only');
    }

    public function test_client_guidelines_do_not_expose_staff_admin_language(): void
    {
        $this->get(route('help.client-guidelines'))
            ->assertOk()
            ->assertSee('Client Portal Guidelines')
            ->assertSee('Registered Email')
            ->assertSee('Your Matters Only')
            ->assertDontSee('Staff Access')
            ->assertDontSee('Request Access')
            ->assertDontSee('administrator');
    }

    public function test_login_and_client_portal_pages_link_to_correct_guidelines(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('User Guidelines')
            ->assertSee(route('help.staff-guidelines'), false)
            ->assertDontSee(route('help.client-guidelines'), false);

        $this->get(route('login', ['portal' => 'client']))
            ->assertOk()
            ->assertSee('User Guidelines')
            ->assertSee(route('help.client-guidelines'), false)
            ->assertDontSee('Request Access')
            ->assertDontSee(route('help.staff-guidelines'), false);

        $this->get(route('client.portal'))
            ->assertOk()
            ->assertSee('User Guidelines')
            ->assertSee(route('help.client-guidelines'), false)
            ->assertDontSee(route('help.staff-guidelines'), false);

        $this->get(route('client.register'))
            ->assertOk()
            ->assertSee('User Guidelines')
            ->assertSee(route('help.client-guidelines'), false)
            ->assertDontSee(route('help.staff-guidelines'), false);
    }
}

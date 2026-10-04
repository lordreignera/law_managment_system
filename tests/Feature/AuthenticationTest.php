<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_logout_ends_session_and_protected_pages_require_login_again(): void
    {
        $user = User::factory()->create();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/client/dashboard')->assertRedirect(route('login'));
        $this->get('/user/profile')->assertRedirect(route('login'));
    }

    public function test_web_pages_cannot_be_stored_in_browser_cache(): void
    {
        $response = $this->get('/login')->assertOk();
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->get('/dashboard')->assertRedirect(route('login'))
            ->assertHeader('Pragma', 'no-cache');
    }
}

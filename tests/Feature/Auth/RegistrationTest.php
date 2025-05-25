<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered_when_no_users_exist()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_returns_404_when_users_exist()
    {
        User::factory()->create();

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register_when_no_users_exist()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.index', absolute: false));
    }

    public function test_registration_returns_404_when_users_exist()
    {
        User::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
        $this->assertGuest();
    }
}

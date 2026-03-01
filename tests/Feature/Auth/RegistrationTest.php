<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $locale;
    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = config('app.locale');
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/'.$this->locale.'/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/'.$this->locale.'/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::homeWithLocale());
    }
}

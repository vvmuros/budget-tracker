<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Test User'): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = $id;
        $socialiteUser->email = $email;
        $socialiteUser->name = $name;

        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        $this->fakeGoogleUser('google-123', 'newuser@example.com', 'New User');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('budget.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => 'google-123',
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->email_verified_at, 'Google-registered accounts should skip our own email verification');
    }

    public function test_existing_password_user_gets_linked_by_email(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $this->assertNull($user->google_id);

        $this->fakeGoogleUser('google-456', 'existing@example.com', 'Existing User');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('budget.index'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-456', $user->fresh()->google_id);
    }

    public function test_returning_google_user_logs_in_via_google_id(): void
    {
        $user = User::factory()->create(['google_id' => 'google-789']);

        $this->fakeGoogleUser('google-789', 'ignored@example.com', 'Ignored Name');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('budget.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_google_auth_redirects_to_login_with_error(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andThrow(new \Exception('boom'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}

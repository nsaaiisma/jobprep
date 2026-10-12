<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_google_for_oauth_registration(): void
    {
        Socialite::fake('google');

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect();
    }

    public function test_google_callback_creates_account_and_authenticates_guest(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-account-123',
            'name' => 'Google Applicant',
            'email' => 'google@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('cv'));
        $this->assertDatabaseHas('users', [
            'name' => 'Google Applicant',
            'email' => 'google@example.com',
            'google_id' => 'google-account-123',
            'avatar' => 'https://example.com/avatar.png',
            'password' => null,
        ]);
        $this->assertAuthenticatedAs(User::where('email', 'google@example.com')->firstOrFail());
    }

    public function test_google_callback_links_an_existing_email_account(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-account-456',
            'email' => 'existing@example.com',
            'avatar' => 'https://example.com/new-avatar.png',
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('cv'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-account-456',
            'avatar' => 'https://example.com/new-avatar.png',
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_google_callback_rejects_provider_response_without_email(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-account-789',
            'email' => null,
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Google tidak memberikan ID akun atau email. Coba metode masuk lain.',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_google_callback_does_not_replace_a_different_linked_account(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => 'google-account-original',
        ]);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-account-other',
            'email' => 'existing@example.com',
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Email ini sudah tertaut dengan akun Google lain.',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'google_id' => 'google-account-original',
        ]);
        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_with_email_and_password(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test Applicant',
            'email' => 'applicant@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertRedirect(route('cv'));

        $user = User::where('email', 'applicant@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Test Applicant',
            'email' => 'applicant@example.com',
        ]);
        $this->assertTrue(Hash::check('StrongPass123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_is_rejected_when_password_confirmation_does_not_match(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Test Applicant',
            'email' => 'applicant@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'DifferentPass123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'password' => 'Konfirmasi password tidak sama.',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_is_rejected_when_email_is_already_registered(): void
    {
        User::factory()->create(['email' => 'applicant@example.com']);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Another Applicant',
            'email' => 'applicant@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'email' => 'Email ini sudah terdaftar. Silakan masuk, atau pakai tombol Google jika dulu daftar lewat Google.',
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }
}

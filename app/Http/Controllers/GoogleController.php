<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['email' => 'Masuk dengan Google dibatalkan atau gagal. Coba lagi.']);
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (blank($googleId) || blank($email)) {
            return redirect()->route('login')->withErrors(['email' => 'Google tidak memberikan ID akun atau email. Coba metode masuk lain.']);
        }

        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user && $user->google_id && $user->google_id !== $googleId) {
                return redirect()->route('login')->withErrors(['email' => 'Email ini sudah tertaut dengan akun Google lain.']);
            }
        }

        if ($user) {
            $user->update([
                'google_id' => $googleId,
                'avatar' => $googleUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleId,
                'avatar' => $googleUser->getAvatar(),
                'password' => null,
            ]);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('cv'));
    }
}

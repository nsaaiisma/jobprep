<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk, atau pakai tombol Google jika dulu daftar lewat Google.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $user = User::create($data);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('cv')->with('status', 'Akun berhasil dibuat. Mulai dengan mengunggah CV kamu.');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);

        if (!Auth::attempt($cred, $r->boolean('remember'))) {
            return back()->withInput($r->only('email'))
                ->withErrors(['email' => 'Email atau password salah. Jika dulu daftar lewat Google, gunakan tombol Google.']);
        }

        $r->session()->regenerate();
        return redirect()->intended(route('cv'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();      // sekaligus menghapus teks CV di session
        $r->session()->regenerateToken();
        return redirect()->route('home');
    }
}

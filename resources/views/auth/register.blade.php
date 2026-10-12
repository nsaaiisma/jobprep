@extends('layouts.app')
@section('title','Daftar')
@section('content')
<div class="auth">
  <h1>Buat akun JobPrep</h1>
  <p class="lead">Gratis. Cukup email atau akun Google.</p>
  <div class="panel">
    @include('auth._google')
    <form method="POST" action="{{ route('register') }}">
      @csrf
      <div class="field"><label for="name">Nama lengkap</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
        @error('name')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="field"><label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@gmail.com" autocomplete="email" required>
        @error('email')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="field"><label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <span class="hint">Minimal 8 karakter, berisi huruf dan angka.</span>
        @error('password')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="field"><label for="password_confirmation">Ulangi password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
      <button class="go" type="submit">Daftar</button>
    </form>
  </div>
  <p class="hint">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
</div>
@endsection

@extends('layouts.app')
@section('title','Masuk')
@section('content')
<div class="auth">
  <h1>Masuk ke JobPrep</h1>
  <p class="lead">Lanjutkan latihan wawancara kamu.</p>
  <div class="panel">
    @include('auth._google')
    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="field"><label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@gmail.com" autocomplete="email" required autofocus>
        @error('email')<p class="err">{{ $message }}</p>@enderror</div>
      <div class="field"><label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required></div>
      <div class="field"><label style="font-weight:500"><input type="checkbox" name="remember" style="width:auto;margin-right:6px">Ingat saya di perangkat ini</label></div>
      <button class="go" type="submit">Masuk</button>
    </form>
  </div>
  <p class="hint">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
</div>
@endsection

@extends('layouts.app')
@section('content')
<h1>Masuk ruang wawancara dengan jawaban yang sudah siap.</h1>
<p class="lead">Unggah CV, latih jawaban untuk pertanyaan recruiter, dan pelajari perusahaan tujuan sebelum hari H.</p>

<div class="panel" aria-label="Contoh percakapan">
  <p class="hint" style="margin:0 0 4px">Recruiter</p>
  <p style="margin:0 0 16px;font-weight:700">"Ceritakan kegagalan terbesar kamu dan apa yang kamu pelajari."</p>
  <p class="hint" style="margin:0 0 4px">Jawaban yang disusun dari CV kamu</p>
  <p style="margin:0;border-left:3px solid var(--mark);padding-left:14px">Di proyek terakhir saya terlambat dua minggu karena tidak memetakan dependensi sejak awal. Saya buat checklist risiko mingguan, dan proyek berikutnya selesai tepat waktu…</p>
</div>

@guest<p style="margin:0 0 24px"><a class="go" style="display:inline-block;text-decoration:none;font-weight:700;background:var(--accent);color:var(--accent-ink);padding:11px 20px;border-radius:8px" href="{{ route('register') }}">Daftar gratis</a> <span class="hint">atau <a href="{{ route('login') }}">masuk</a></span></p>@endguest

<div class="grid2">
  <a class="panel" style="text-decoration:none;color:inherit" href="{{ route('cv') }}"><b>1. Analisis CV</b><br><span class="hint">Skill, kekuatan, gap, skor kecocokan, dan prediksi pertanyaan.</span></a>
  <a class="panel" style="text-decoration:none;color:inherit" href="{{ route('interview') }}"><b>2. Jawab pertanyaan</b><br><span class="hint">Jawaban gaya STAR yang disesuaikan dengan pengalamanmu.</span></a>
  <a class="panel" style="text-decoration:none;color:inherit" href="{{ route('company') }}"><b>3. Riset perusahaan</b><br><span class="hint">Bisnis, budaya, berita terbaru, dan pertanyaan untuk pewawancara.</span></a>
</div>
@endsection

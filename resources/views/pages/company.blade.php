@extends('layouts.app')
@section('title','Riset perusahaan')
@section('content')
<h1>Kenali perusahaannya sebelum mereka mengenalimu.</h1>
<p class="lead">Masukkan nama perusahaan. Informasi dicari dari web, jadi selalu cek ulang sumbernya untuk data penting.</p>

<form id="f" class="panel">
  <div class="row">
    <div class="field"><label for="company">Nama perusahaan</label><input id="company" name="company" required placeholder="mis. PT Telkom Indonesia"></div>
    <div class="field"><label for="role">Posisi yang dilamar (opsional)</label><input id="role" name="role"></div>
  </div>
  <button class="go" type="submit">Cari informasi</button>
</form>
<div id="out" class="panel out" hidden></div>
@endsection
@push('scripts')
<script>
document.getElementById('f').addEventListener('submit',async e=>{
  e.preventDefault();const out=document.getElementById('out');out.hidden=false;
  const d=await send('{{ route('company') }}',Object.fromEntries(new FormData(e.target)),e.submitter,out);
  if(d)out.innerHTML=md(d.answer);
});
</script>
@endpush

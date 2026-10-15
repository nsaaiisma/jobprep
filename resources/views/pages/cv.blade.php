@extends('layouts.app')
@section('title','Analisis CV')
@section('content')
<h1>Lihat CV kamu seperti recruiter melihatnya.</h1>
<p class="lead">Unggah CV (PDF, DOCX, atau TXT, maks 5 MB). Hasilnya juga dipakai untuk menyesuaikan jawaban di halaman wawancara.</p>

<form id="f" class="panel">
  <div class="field"><label for="cv">File CV</label><input id="cv" name="cv" type="file" accept=".pdf,.docx,.txt" required></div>
  <div class="row">
    <div class="field"><label for="role">Posisi yang dilamar (opsional)</label><input id="role" name="role" placeholder="mis. Backend Developer"></div>
  </div>
  <div class="field"><label for="jd">Deskripsi lowongan (opsional)</label><textarea id="jd" name="job_desc" placeholder="Tempel deskripsi lowongan untuk mendapat skor kecocokan"></textarea></div>
  <button class="go" type="submit">Analisis CV</button>
</form>
<div id="out"></div>
@endsection
@push('scripts')
<script>
const list=a=>'<ul>'+(a||[]).map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>';
const chips=a=>'<ul class="chips">'+(a||[]).map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>';
document.getElementById('f').addEventListener('submit',async e=>{
  e.preventDefault();const out=document.getElementById('out');
  const file=document.getElementById('cv').files[0];
  if(file&&file.size>5*1024*1024){out.innerHTML='<p class="err">File lebih dari 5 MB. Kecilkan dulu agar cepat diproses.</p>';return;}
  out.innerHTML='<div class="panel out"><p>⏳ Menganalisis CV… biasanya 10-25 detik. Jangan tutup halaman ini.</p></div>';
  const d=await send('{{ route('cv') }}',new FormData(e.target),e.submitter,out);if(!d)return;
  let h='<div class="panel out"><b>'+esc(d.level)+'</b><p>'+esc(d.ringkasan)+'</p></div>';
  if(d.skor_kecocokan!=null)h+='<div class="panel out"><div class="score">'+esc(d.skor_kecocokan)+'<small style="font-size:20px">/100</small></div><div class="bar"><i style="width:'+Number(d.skor_kecocokan)+'%"></i></div><p>'+esc(d.alasan_skor)+'</p></div>';
  h+='<div class="panel out"><b>Skill teknis</b>'+chips(d.skill_teknis)+'<br><b>Skill non-teknis</b>'+chips(d.skill_non_teknis)+'</div>';
  h+='<div class="grid2"><div class="panel out"><b>Kekuatan</b>'+list(d.kekuatan)+'</div><div class="panel out"><b>Yang perlu dilengkapi</b>'+list(d.gap)+'</div></div>';
  h+='<div class="panel out"><b>Saran perbaikan CV</b>'+list(d.saran_perbaikan_cv)+'</div>';
  h+='<div class="panel out"><b>Kemungkinan pertanyaan recruiter</b><p class="hint">Klik untuk melatih jawabannya.</p>'+(d.prediksi_pertanyaan||[]).map(q=>'<button type="button" class="q" data-q="'+esc(q)+'">'+esc(q)+'</button>').join('')+'</div>';
  out.innerHTML=h;
  out.querySelectorAll('.q').forEach(b=>b.onclick=()=>location.href='{{ route('interview') }}?q='+encodeURIComponent(b.dataset.q));
});
</script>
@endpush

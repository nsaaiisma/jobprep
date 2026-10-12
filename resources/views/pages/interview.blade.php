@extends('layouts.app')
@section('title','Jawab pertanyaan')
@section('content')
<h1>Tempel pertanyaan recruiter, dapat jawaban yang siap diucapkan.</h1>
<p class="lead">
  @if($hasCv) CV kamu sudah terbaca, jadi jawaban memakai pengalaman nyata dari CV.
  @else Belum ada CV. <a href="{{ route('cv') }}">Unggah CV</a> agar jawaban lebih personal. @endif
</p>

<form id="f" class="panel">
  <div class="field"><label for="question">Pertanyaan dari recruiter</label><textarea id="question" name="question" required maxlength="1000" placeholder="mis. Kenapa kami harus merekrut kamu?"></textarea></div>
  <div class="row">
    <div class="field"><label for="role">Posisi</label><input id="role" name="role"></div>
    <div class="field"><label for="company">Perusahaan</label><input id="company" name="company"></div>
  </div>
  <div class="row">
    <div class="field"><label for="style">Gaya jawaban</label><select id="style" name="style"><option value="star">STAR (terstruktur)</option><option value="singkat">Singkat</option><option value="formal">Formal</option></select></div>
    <div class="field"><label for="language">Bahasa</label><select id="language" name="language"><option value="id">Indonesia</option><option value="en">English</option></select></div>
  </div>
  <button class="go" type="submit">Buat jawaban</button>
</form>
<div id="out" class="panel out" hidden></div>

<section id="live-audio-panel" class="panel" aria-labelledby="live-title">
  <h2 id="live-title" style="margin-top:0">Bantuan audio langsung</h2>
  <p class="hint">Gunakan mikrofon default sistem tanpa memilih perangkat. Klik Generate dan izinkan akses mikrofon satu kali. Setelah aktif, JobPrep tetap mendengarkan saat Anda berpindah ke GMeet selama halaman atau jendela JobPrep tetap terbuka. Suara peserta lain hanya tertangkap jika masuk ke mikrofon; aplikasi tidak merekam suara tab GMeet secara langsung. Audio diproses otomatis per 6 detik saat suara terdeteksi dan tidak disimpan. Klik Selesai untuk menghentikan dan memproses segmen terakhir.</p>
  <p class="hint">Mikrofon aktif: <strong id="microphone-name">Belum terhubung</strong></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <button class="go" type="button" id="generate-live-audio">Generate</button>
    <button type="button" id="finish-live-audio" disabled>Selesai</button>
    <button type="button" id="open-floating-window">Buka jendela melayang</button>
  </div>
  <p id="audio-status" class="hint" role="status" aria-live="polite">Klik Generate untuk mulai mendengarkan mikrofon.</p>
  <label for="audio-level">Level suara masuk</label>
  <meter id="audio-level" min="0" max="0.05" low="0.004" high="0.015" optimum="0.025" value="0"></meter>
  <p id="audio-detection-status" class="hint" role="status" aria-live="polite">Menunggu audio.</p>
  <div id="live-audio-output" class="out" hidden>
    <div class="panel">
      <strong>Transkrip terkini</strong>
      <p id="live-transcript" style="white-space:pre-wrap">Belum ada ucapan yang dianalisis.</p>
    </div>
    <div class="panel">
      <strong>Saran jawaban</strong>
      <div id="live-answer"></div>
    </div>
  </div>
</section>
@endsection
@push('scripts')
<script>
const q=new URLSearchParams(location.search).get('q');if(q)document.getElementById('question').value=q;
document.getElementById('f').addEventListener('submit',async e=>{
  e.preventDefault();const out=document.getElementById('out');out.hidden=false;
  const body=Object.fromEntries(new FormData(e.target));
  const d=await send('{{ route('interview') }}',body,e.submitter,out);
  if(d)out.innerHTML=md(d.answer);
});

const generateLiveAudio=document.getElementById('generate-live-audio');
const finishLiveAudio=document.getElementById('finish-live-audio');
const audioStatus=document.getElementById('audio-status');
const microphoneName=document.getElementById('microphone-name');
const audioLevel=document.getElementById('audio-level');
const audioDetectionStatus=document.getElementById('audio-detection-status');
const liveAudioOutput=document.getElementById('live-audio-output');
const liveTranscript=document.getElementById('live-transcript');
const liveAnswer=document.getElementById('live-answer');
const openFloatingWindowButton=document.getElementById('open-floating-window');
const audioChunkUrl=@json(route('interview.audio'));
const samplesPerChunk=16000*6;
const speechRmsThreshold=0.0025;
const speechPeakThreshold=0.008;
let liveStream=null;
let audioContext=null;
let processor=null;
let silenceGain=null;
let sampleBuffer=[];
let chunkHasSpeech=false;
let transcriptContext='';
let audioQueue=Promise.resolve();
let pendingAudioChunks=0;
let recording=false;
let floatingWindowReference=null;
let audioFailure='';

function openFloatingWindow(){
  if(floatingWindowReference&&!floatingWindowReference.closed){
    floatingWindowReference.focus();
    return;
  }

  const popupUrl=new URL(window.location.href);
  popupUrl.searchParams.set('audio_popup','1');
  floatingWindowReference=window.open(
    popupUrl.href,
    'jobprep-audio-popup',
    'popup=yes,width=560,height=760,resizable=yes,scrollbars=yes'
  );
  if(!floatingWindowReference){
    audioStatus.textContent='Jendela melayang diblokir browser. Izinkan pop-up untuk JobPrep lalu coba lagi.';
  }
}

openFloatingWindowButton.addEventListener('click',openFloatingWindow);

if(new URLSearchParams(window.location.search).get('audio_popup')==='1'){
  document.title='Bantuan audio langsung — JobPrep';
  document.body.style.display='block';
  document.body.style.minHeight='100vh';
  document.querySelector('nav').hidden=true;
  const main=document.querySelector('main');
  main.style.maxWidth='none';
  main.style.padding='12px';
  for(const child of main.children){
    if(child.id!=='live-audio-panel')child.hidden=true;
  }
  liveAudioOutput.hidden=false;
  openFloatingWindowButton.hidden=true;
}

async function connectAudioInput(){
  liveStream=await navigator.mediaDevices.getUserMedia({
    audio:{
      channelCount:1,
      echoCancellation:false,
      noiseSuppression:false,
      autoGainControl:true
    },
    video:false
  });
  microphoneName.textContent=liveStream.getAudioTracks()[0]?.label||'Mikrofon default sistem';
  audioContext=new AudioContext();
  await audioContext.resume();
  const source=audioContext.createMediaStreamSource(liveStream);
  processor=audioContext.createScriptProcessor(4096,1,1);
  silenceGain=audioContext.createGain();
  silenceGain.gain.value=0;
  processor.onaudioprocess=event=>{
    if(!recording)return;
    const inputSamples=event.inputBuffer.getChannelData(0);
    let energy=0;
    let peak=0;
    for(const sample of inputSamples){
      energy+=sample*sample;
      peak=Math.max(peak,Math.abs(sample));
    }
    const rms=Math.sqrt(energy/inputSamples.length);
    audioLevel.value=Math.min(rms,Number(audioLevel.max));
    if(rms>=speechRmsThreshold||peak>=speechPeakThreshold){
      chunkHasSpeech=true;
      audioDetectionStatus.textContent='Suara terdeteksi. Kalimat akan dikirim untuk ditranskripsikan.';
      audioStatus.textContent='Suara terdeteksi; membuat transkrip dan saran otomatis…';
    }else{
      audioDetectionStatus.textContent='Mendengarkan; suara masih terlalu pelan atau belum terdeteksi.';
    }
    const samples=resampleTo16k(inputSamples,audioContext.sampleRate);
    for(const sample of samples)sampleBuffer.push(sample);
    while(sampleBuffer.length>=samplesPerChunk){
      const chunk=sampleBuffer.slice(0,samplesPerChunk);
      if(chunkHasSpeech)queueAudioChunk(encodeWav(Float32Array.from(chunk)));
      sampleBuffer=sampleBuffer.slice(samplesPerChunk);
      chunkHasSpeech=false;
    }
  };
  source.connect(processor);
  processor.connect(silenceGain);
  silenceGain.connect(audioContext.destination);
}

generateLiveAudio.addEventListener('click',async()=>{
  if(!navigator.mediaDevices?.getUserMedia){
    audioStatus.textContent='Browser ini tidak mendukung pemilihan perangkat audio.';
    return;
  }
  generateLiveAudio.disabled=true;
  liveTranscript.textContent='Mendengarkan; transkrip akan tampil setelah ucapan diproses.';
  liveAnswer.replaceChildren();
  transcriptContext='';
  audioFailure='';
  audioStatus.classList.remove('err');
  audioDetectionStatus.classList.remove('err');
  liveAudioOutput.hidden=false;
  audioLevel.value=0;
  audioDetectionStatus.textContent='Meminta izin mikrofon…';
  audioStatus.textContent='Meminta izin audio dan mulai mendengarkan…';
  try{
    recording=true;
    await connectAudioInput();
    finishLiveAudio.disabled=false;
    audioDetectionStatus.textContent='Input audio aktif. Bicara atau putar suara Zoom untuk mulai mendeteksi.';
    audioStatus.textContent='Sedang mendengarkan; saran akan dibuat otomatis saat ada suara.';
  }catch(error){
    recording=false;
    processor?.disconnect();
    silenceGain?.disconnect();
    liveStream?.getTracks().forEach(track=>track.stop());
    liveStream=null;
    microphoneName.textContent='Belum terhubung';
    processor=null;
    silenceGain=null;
    if(audioContext){
      await audioContext.close();
      audioContext=null;
    }
    finishLiveAudio.disabled=true;
    generateLiveAudio.disabled=false;
    audioLevel.value=0;
    audioDetectionStatus.textContent='Tidak ada audio yang sedang didengarkan.';
    audioStatus.textContent=`Tidak dapat memulai audio: ${error.message}`;
  }
});

finishLiveAudio.addEventListener('click',async()=>{
  recording=false;
  finishLiveAudio.disabled=true;
  generateLiveAudio.disabled=true;
  audioDetectionStatus.textContent='Menutup mikrofon dan mengirim ucapan terakhir…';
  processor?.disconnect();
  silenceGain?.disconnect();
  liveStream?.getTracks().forEach(track=>track.stop());
  liveStream=null;
  microphoneName.textContent='Tidak terhubung';
  processor=null;
  silenceGain=null;
  if(sampleBuffer.length>0&&chunkHasSpeech){
    queueAudioChunk(encodeWav(Float32Array.from(sampleBuffer)));
  }
  sampleBuffer=[];
  chunkHasSpeech=false;
  audioLevel.value=0;
  if(audioContext){
    await audioContext.close();
    audioContext=null;
  }
  audioStatus.textContent='Menganalisis ucapan terakhir…';
  await audioQueue;
  if(audioFailure){
    audioDetectionStatus.textContent=`Analisis audio gagal: ${audioFailure}`;
    audioDetectionStatus.classList.add('err');
  }else if(!transcriptContext){
    liveTranscript.textContent='Tidak ada kalimat yang terdeteksi. Periksa level suara dan pastikan perangkat input menangkap audio Zoom.';
    audioDetectionStatus.textContent='Tidak ada transkrip. Coba pilih perangkat audio Zoom atau naikkan volumenya.';
  }else{
    audioDetectionStatus.textContent='Transkrip selesai. Kalimat terdeteksi ditampilkan di atas.';
  }
  generateLiveAudio.disabled=false;
  audioStatus.textContent=audioFailure
    ? `Selesai, tetapi analisis audio gagal: ${audioFailure}`
    : 'Selesai. Klik Generate untuk mulai lagi.';
  audioStatus.classList.toggle('err',Boolean(audioFailure));
});

function resampleTo16k(samples,inputRate){
  const ratio=inputRate/16000;
  const outputLength=Math.floor(samples.length/ratio);
  const output=new Float32Array(outputLength);
  for(let index=0;index<outputLength;index++){
    const position=index*ratio;
    const before=Math.floor(position);
    const fraction=position-before;
    const after=Math.min(before+1,samples.length-1);
    output[index]=samples[before]*(1-fraction)+samples[after]*fraction;
  }
  return output;
}

function encodeWav(samples){
  const buffer=new ArrayBuffer(44+samples.length*2);
  const view=new DataView(buffer);
  const writeText=(offset,text)=>{for(let i=0;i<text.length;i++)view.setUint8(offset+i,text.charCodeAt(i));};
  writeText(0,'RIFF');
  view.setUint32(4,36+samples.length*2,true);
  writeText(8,'WAVE');
  writeText(12,'fmt ');
  view.setUint32(16,16,true);
  view.setUint16(20,1,true);
  view.setUint16(22,1,true);
  view.setUint32(24,16000,true);
  view.setUint32(28,32000,true);
  view.setUint16(32,2,true);
  view.setUint16(34,16,true);
  writeText(36,'data');
  view.setUint32(40,samples.length*2,true);
  for(let index=0;index<samples.length;index++){
    const sample=Math.max(-1,Math.min(1,samples[index]));
    view.setInt16(44+index*2,sample<0?sample*0x8000:sample*0x7fff,true);
  }
  return new Blob([buffer],{type:'audio/wav'});
}

function queueAudioChunk(blob){
  pendingAudioChunks++;
  audioStatus.textContent=pendingAudioChunks>1
    ? `${pendingAudioChunks} segmen audio menunggu analisis…`
    : 'Menganalisis segmen audio…';
  audioQueue=audioQueue.then(async()=>{
    const form=new FormData();
    const interviewForm=document.getElementById('f');
    form.append('audio',blob,'interview-segment.wav');
    form.append('context',transcriptContext.slice(-4000));
    form.append('role',interviewForm.elements.role.value);
    form.append('company',interviewForm.elements.company.value);
    form.append('language',interviewForm.elements.language.value);
    const response=await fetch(audioChunkUrl,{
      method:'POST',
      headers:{
        'X-CSRF-TOKEN':csrf,
        'Accept':'application/json'
      },
      body:form
    });
    const data=await response.json();
    if(!response.ok)throw new Error(data.error||data.message||'Analisis audio gagal. Coba lagi.');
    if(data.transcript){
      transcriptContext=(transcriptContext+' '+data.transcript).trim().slice(-5000);
      liveTranscript.textContent=transcriptContext;
      audioDetectionStatus.textContent=`Kalimat terdeteksi: ${data.transcript}`;
      audioDetectionStatus.classList.remove('err');
    }else{
      audioDetectionStatus.textContent='Segmen diproses, tetapi tidak ada kalimat jelas yang terdeteksi.';
    }
    if(data.question_complete&&data.suggested_answer){
      liveAnswer.innerHTML=md(data.suggested_answer);
    }
    liveAudioOutput.hidden=false;
    if(recording&&!audioFailure){
      audioStatus.textContent=data.question_complete
        ? 'Pertanyaan terdeteksi; saran jawaban diperbarui.'
        : 'Audio aktif; menunggu pertanyaan lengkap.';
    }
  }).catch(error=>{
    audioFailure=error.message;
    audioDetectionStatus.textContent=`Analisis audio gagal: ${audioFailure}`;
    audioDetectionStatus.classList.add('err');
    audioStatus.textContent=`Analisis audio gagal: ${error.message}`;
    audioStatus.classList.add('err');
  }).finally(()=>{
    pendingAudioChunks--;
  });
}

</script>
@endpush

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
  <p class="hint">Klik <b>Generate</b> dan izinkan akses mikrofon satu kali. Supaya tetap mendengarkan saat Anda pindah ke GMeet, klik <b>Buka jendela melayang</b> dan biarkan jendela kecil itu terlihat (browser membatasi mic pada tab yang tersembunyi). Opsional: klik <b>Ambil audio GMeet</b> lalu pilih tab GMeet dan aktifkan "Share tab audio" agar suara recruiter tertangkap langsung tanpa lewat pengeras suara. Audio diproses per 6 detik saat suara terdeteksi dan tidak disimpan.</p>
  <p class="hint">Mikrofon: <strong id="microphone-name">Belum terhubung</strong><br>Audio GMeet: <strong id="tab-audio-name">Tidak diambil</strong></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <button class="go" type="button" id="generate-live-audio">Generate</button>
    <button type="button" id="toggle-tab-audio">Ambil audio GMeet</button>
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
const toggleTabAudioButton=document.getElementById('toggle-tab-audio');
const tabAudioName=document.getElementById('tab-audio-name');
const audioChunkUrl=@json(route('interview.audio'));
const samplesPerChunk=16000*6;
const speechRmsThreshold=0.0025;
const speechPeakThreshold=0.008;
let liveStream=null;
let tabStream=null;
let tabSourceNode=null;
let hiddenVideoElement=null;
let audioContext=null;
let workletNode=null;
let silenceGain=null;
let transcriptContext='';
let audioQueue=Promise.resolve();
let pendingAudioChunks=0;
let recording=false;
let floatingWindowReference=null;
let audioFailure='';
let visibilityWatchdog=null;

// AudioWorklet (bukan ScriptProcessor usang): berjalan di thread audio khusus
// sehingga penangkapan suara jauh lebih tahan saat tab/window di-background.
const captureWorkletCode=[
'class JobprepCapture extends AudioWorkletProcessor {',
'  constructor(o){ super(); const p=(o&&o.processorOptions)||{}; this.ratio=(p.inputRate||48000)/16000; this.target=p.samplesPerChunk||96000; this.rmsThr=p.rmsThreshold||0.0025; this.peakThr=p.peakThreshold||0.008; this.buf=[]; this.sbuf=[]; this.inbuf=[]; this.pos=0; }',
'  process(inputs){',
'    const c0=(inputs[0]&&inputs[0][0])||null, c1=(inputs[1]&&inputs[1][0])||null;',
'    if(!c0&&!c1)return true;',
'    const n=Math.max(c0?c0.length:0,c1?c1.length:0);',
'    let energy=0, peak=0;',
'    for(let i=0;i<n;i++){ const a=c0?(c0[i]||0):0, b=c1?(c1[i]||0):0; const s=c1?(a+b)/2:a; this.inbuf.push(s); energy+=s*s; const v=s<0?-s:s; if(v>peak)peak=v; }',
'    const rms=Math.sqrt(energy/Math.max(n,1));',
'    let fired=0;',
'    if(rms>=this.rmsThr||peak>=this.peakThr)fired=1;',
'    const out=[];',
'    while(this.pos+1<this.inbuf.length){ const b=Math.floor(this.pos), f=this.pos-b; out.push(this.inbuf[b]*(1-f)+this.inbuf[b+1]*f); this.pos+=this.ratio; }',
'    const drop=Math.floor(this.pos);',
'    if(drop>0){ this.inbuf.splice(0,drop); this.pos-=drop; }',
'    for(let i=0;i<out.length;i++){ this.buf.push(out[i]); this.sbuf.push(fired); }',
'    this.port.postMessage({type:"level",rms:rms});',
'    while(this.buf.length>=this.target){ const s=new Float32Array(this.buf.slice(0,this.target)); let marked=0; for(let k=0;k<this.target;k++)marked+=this.sbuf[k]; this.buf=this.buf.slice(this.target); this.sbuf=this.sbuf.slice(this.target); this.port.postMessage({type:"chunk",samples:s,hasSpeech:marked>=2400},[s.buffer]); }',
'    return true;',
'  }',
'}',
'registerProcessor("jobprep-capture",JobprepCapture);'
].join('\n');

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

function attachTabSource(){
  if(!tabStream||!audioContext||!workletNode||tabSourceNode)return;
  const audioTracks=tabStream.getAudioTracks();
  if(audioTracks.length===0)return;
  tabSourceNode=audioContext.createMediaStreamSource(tabStream);
  tabSourceNode.connect(workletNode,0,1);
}

function detachTabSource(){
  try{tabSourceNode?.disconnect();}catch(e){}
  tabSourceNode=null;
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
  const moduleUrl=URL.createObjectURL(new Blob([captureWorkletCode],{type:'application/javascript'}));
  try{
    await audioContext.audioWorklet.addModule(moduleUrl);
  }finally{
    URL.revokeObjectURL(moduleUrl);
  }
  workletNode=new AudioWorkletNode(audioContext,'jobprep-capture',{
    numberOfInputs:2,
    numberOfOutputs:1,
    processorOptions:{
      inputRate:audioContext.sampleRate,
      samplesPerChunk:samplesPerChunk,
      rmsThreshold:speechRmsThreshold,
      peakThreshold:speechPeakThreshold
    }
  });
  workletNode.port.onmessage=event=>{
    const message=event.data||{};
    if(message.type==='level'){
      audioLevel.value=Math.min(message.rms||0,Number(audioLevel.max));
      return;
    }
    if(message.type==='chunk'){
      if(!recording)return;
      if(message.hasSpeech){
        queueAudioChunk(encodeWav(message.samples));
        audioDetectionStatus.textContent='Suara terdeteksi. Kalimat akan dikirim untuk ditranskripsikan.';
        audioStatus.textContent='Suara terdeteksi; membuat transkrip dan saran otomatis…';
      }else{
        audioDetectionStatus.textContent='Mendengarkan; suara masih terlalu pelan atau belum terdeteksi.';
      }
    }
  };
  const micSource=audioContext.createMediaStreamSource(liveStream);
  micSource.connect(workletNode,0,0);
  silenceGain=audioContext.createGain();
  silenceGain.gain.value=0;
  workletNode.connect(silenceGain);
  silenceGain.connect(audioContext.destination);
  attachTabSource();
}

function stopAudioGraph(){
  recording=false;
  try{workletNode?.disconnect();}catch(e){}
  try{silenceGain?.disconnect();}catch(e){}
  detachTabSource();
  liveStream?.getTracks().forEach(track=>track.stop());
  liveStream=null;
  workletNode=null;
  silenceGain=null;
}

toggleTabAudioButton.addEventListener('click',async()=>{
  if(tabStream){
    tabStream.getTracks().forEach(track=>track.stop());
    tabStream=null;
    detachTabSource();
    if(hiddenVideoElement){hiddenVideoElement.srcObject=null;hiddenVideoElement.remove();hiddenVideoElement=null;}
    tabAudioName.textContent='Tidak diambil';
    toggleTabAudioButton.textContent='Ambil audio GMeet';
    audioStatus.textContent=recording?'Hanya memakai mikrofon sekarang.':'Klik Generate untuk mulai mendengarkan mikrofon.';
    return;
  }
  if(!navigator.mediaDevices?.getDisplayMedia){
    audioStatus.textContent='Browser ini tidak mendukung pengambilan audio tab.';
    return;
  }
  try{
    audioStatus.textContent='Pilih tab GMeet dan aktifkan "Share tab audio", lalu klik Share…';
    tabStream=await navigator.mediaDevices.getDisplayMedia({audio:{echoCancellation:false,noiseSuppression:false},video:true});
  }catch(error){
    audioStatus.textContent='Pengambilan audio GMeet dibatalkan. Mikrofon tetap bisa dipakai sendiri.';
    return;
  }
  const videoTracks=tabStream.getVideoTracks();
  if(videoTracks.length>0){
    hiddenVideoElement=document.createElement('video');
    hiddenVideoElement.muted=true;
    hiddenVideoElement.playsInline=true;
    hiddenVideoElement.style.cssText='position:fixed;width:2px;height:2px;opacity:0;pointer-events:none;';
    hiddenVideoElement.srcObject=new MediaStream(videoTracks);
    document.body.appendChild(hiddenVideoElement);
    hiddenVideoElement.play().catch(()=>{});
  }
  tabStream.getAudioTracks().forEach(track=>{
    tabAudioName.textContent=track.label||'Audio tab dibagikan';
    track.addEventListener('ended',()=>{
      tabStream=null;
      detachTabSource();
      tabAudioName.textContent='Berhenti dibagikan';
      toggleTabAudioButton.textContent='Ambil audio GMeet';
    });
  });
  if(tabStream.getAudioTracks().length===0){
    tabAudioName.textContent='Tab dibagikan tanpa audio. Ulangi dan aktifkan "Share tab audio".';
  }
  attachTabSource();
  toggleTabAudioButton.textContent='Hentikan audio GMeet';
  if(recording)audioStatus.textContent='Mendengarkan mikrofon + audio GMeet.';
});

document.addEventListener('visibilitychange',()=>{
  if(!recording)return;
  if(document.hidden){
    audioStatus.textContent='Tab tersembunyi — browser membatasi mic di latar belakang. Biarkan jendela melayang terlihat agar suara tetap tertangkap.';
    audioStatus.classList.add('err');
  }else{
    audioStatus.classList.remove('err');
    if(audioContext&&audioContext.state!=='running')audioContext.resume().catch(()=>{});
    audioStatus.textContent='Sedang mendengarkan; saran akan dibuat otomatis saat ada suara.';
  }
});

visibilityWatchdog=setInterval(()=>{
  if(recording&&audioContext&&audioContext.state!=='running'){
    audioContext.resume().catch(()=>{});
  }
},5000);

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
    stopAudioGraph();
    microphoneName.textContent='Belum terhubung';
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
  finishLiveAudio.disabled=true;
  generateLiveAudio.disabled=true;
  audioDetectionStatus.textContent='Menutup mikrofon dan mengirim ucapan terakhir…';
  const hadTabStream=Boolean(tabStream);
  stopAudioGraph();
  microphoneName.textContent='Tidak terhubung';
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

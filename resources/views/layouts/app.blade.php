<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'JobPrep') — Persiapan Wawancara Kerja</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
<style>
:root{--bg:#EEF1F5;--panel:#fff;--ink:#16202C;--muted:#5B6776;--line:#D5DBE3;--accent:#0B6E6E;--accent-ink:#fff;--mark:#F2B632;--bad:#B3261E;--r:10px}
@media (prefers-color-scheme:dark){:root{--bg:#10161D;--panel:#18212B;--ink:#E7ECF2;--muted:#9AA7B6;--line:#2B3846;--accent:#3FB6B0;--accent-ink:#06201F;--bad:#FF8A80}}
*{box-sizing:border-box}
body{margin:0;font:16px/1.65 'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg);color:var(--ink);display:grid;grid-template-columns:230px 1fr;min-height:100vh}
nav{padding:28px 20px;border-right:1px solid var(--line);position:sticky;top:0;height:100vh}
.brand{font-weight:800;font-size:20px;letter-spacing:-.02em;text-decoration:none;color:var(--ink);display:block;margin-bottom:28px}
.brand span{background:var(--mark);color:#16202C;padding:0 6px;border-radius:4px;margin-right:2px}
nav a.l{display:block;padding:10px 12px;border-radius:var(--r);color:var(--muted);text-decoration:none;font-weight:500;margin-bottom:4px}
nav a.l:hover{color:var(--ink)}nav a.l.on{background:var(--panel);color:var(--ink);font-weight:700;box-shadow:inset 3px 0 0 var(--accent)}
main{padding:40px clamp(20px,5vw,64px);max-width:920px;width:100%}
h1{font-size:clamp(28px,4vw,40px);line-height:1.15;letter-spacing:-.03em;margin:0 0 8px;font-weight:800}
.lead{color:var(--muted);max-width:60ch;margin:0 0 28px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:22px;margin-bottom:20px}
label{display:block;font-weight:700;font-size:14px;margin:0 0 6px}
input,textarea,select{width:100%;font:inherit;color:var(--ink);background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:10px 12px}
textarea{min-height:110px;resize:vertical}
input:focus,textarea:focus,select:focus,button:focus-visible,a:focus-visible{outline:3px solid var(--accent);outline-offset:1px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}.field{margin-bottom:14px}.row .field{margin:0}
button.go{font:inherit;font-weight:700;background:var(--accent);color:var(--accent-ink);border:0;border-radius:8px;padding:11px 20px;cursor:pointer}
button.go[disabled]{opacity:.6;cursor:wait}
.hint{font-size:14px;color:var(--muted)}.err{color:var(--bad);font-weight:500}
.out{line-height:1.7}.out h1,.out h2,.out h3{font-size:18px;margin:18px 0 6px}.out ul{padding-left:20px}.out a{color:var(--accent)}
.chips{display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:8px 0 0;list-style:none}
.chips li{background:var(--bg);border:1px solid var(--line);border-radius:99px;padding:3px 12px;font-size:14px}
.score{font-size:56px;font-weight:800;letter-spacing:-.04em;line-height:1}
.bar{height:8px;background:var(--bg);border-radius:9px;overflow:hidden;margin:10px 0}.bar i{display:block;height:100%;background:var(--accent)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.q{display:block;width:100%;text-align:left;font:inherit;background:none;border:0;border-bottom:1px solid var(--line);padding:10px 0;cursor:pointer;color:var(--ink)}.q:hover{color:var(--accent)}
@media(max-width:760px){body{grid-template-columns:1fr}nav{position:static;height:auto;display:flex;gap:6px;flex-wrap:wrap;border:0;padding:16px}.brand{width:100%;margin:0 0 6px}.row,.grid2{grid-template-columns:1fr}}
@media(prefers-reduced-motion:no-preference){.out{animation:in .25s ease-out}@keyframes in{from{opacity:0}}}

.auth{max-width:420px}.auth .panel{padding:24px}
.g-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--ink);font-weight:700;text-decoration:none}
.g-btn:hover{border-color:var(--accent)}
.sep{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:14px;margin:18px 0}.sep:before,.sep:after{content:"";flex:1;border-top:1px solid var(--line)}
.who{margin-top:28px;padding-top:16px;border-top:1px solid var(--line);font-size:14px}.who b{display:block;overflow:hidden;text-overflow:ellipsis}
.who img{width:28px;height:28px;border-radius:50%;vertical-align:middle;margin-right:8px}
.who button{font:inherit;background:none;border:0;color:var(--muted);padding:0;cursor:pointer;text-decoration:underline}
.flash{background:var(--panel);border-left:3px solid var(--accent);padding:10px 14px;border-radius:8px;margin-bottom:20px}
</style>
</head>
<body>
<nav>
  <a class="brand" href="{{ route('home') }}"><span>Job</span>Prep</a>
  <a class="l {{ request()->routeIs('cv') ? 'on' : '' }}" href="{{ route('cv') }}">Analisis CV</a>
  <a class="l {{ request()->routeIs('interview') ? 'on' : '' }}" href="{{ route('interview') }}">Jawab pertanyaan</a>
  <a class="l {{ request()->routeIs('company') ? 'on' : '' }}" href="{{ route('company') }}">Riset perusahaan</a>
  @auth
  <div class="who">
    <b>@if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar }}" alt="" referrerpolicy="no-referrer">@endif{{ auth()->user()->name }}</b>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
  </div>
  @else
  <div class="who"><a class="l" href="{{ route('login') }}">Masuk</a><a class="l" href="{{ route('register') }}">Daftar</a></div>
  @endauth
</nav>
<main>@if(session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif @yield('content')</main>
<script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const md=t=>DOMPurify.sanitize(marked.parse(t));
const esc=s=>{const d=document.createElement('div');d.textContent=s??'';return d.innerHTML};
async function send(url,body,btn,out){
  btn.disabled=true;const old=btn.textContent;btn.textContent='Memproses…';out.innerHTML='';
  try{
    const res=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json',...(body instanceof FormData?{}:{'Content-Type':'application/json'})},body:body instanceof FormData?body:JSON.stringify(body)});
    const data=await res.json();
    if(!res.ok){throw new Error(data.error||Object.values(data.errors||{})[0]?.[0]||'Terjadi kesalahan, coba lagi.')}
    return data;
  }catch(e){out.innerHTML='<p class="err">'+esc(e.message)+'</p>';return null}
  finally{btn.disabled=false;btn.textContent=old}
}
</script>
@stack('scripts')
</body>
</html>

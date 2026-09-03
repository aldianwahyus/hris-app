<!DOCTYPE html>
<html lang="id" data-tema="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — HCIS Bank NTB Syariah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B; --hijau-muda:#E6F2ED;
  --emas:#C9A227; --emas-muda:#FBF4DE;
  --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5;
  --teks:#0F1F1A; --teks-lemah:#5C706A;
  --merah:#B42318; --merah-muda:#FEF3F2;
  --r:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--putih);
  color:var(--teks);min-height:100vh}
:focus-visible{outline:2px solid var(--emas);outline-offset:2px}

.split{display:flex;min-height:100vh}

/* ---- Animasi masuk ---- */
@keyframes masuk{
  from{opacity:0;transform:translateY(14px)}
  to{opacity:1;transform:translateY(0)}
}
@keyframes kembang{
  from{opacity:0;transform:scale(.94)}
  to{opacity:1;transform:scale(1)}
}
@keyframes hela{
  from{transform:scale(1.08)}
  to{transform:scale(1)}
}
.anim{opacity:0;animation:masuk .6s cubic-bezier(.22,.61,.36,1) forwards}
.logo.anim{animation-delay:.05s}
.jd.anim{animation-delay:.15s}
.sb.anim{animation-delay:.22s}
form .bidang:nth-of-type(1).anim{animation-delay:.29s}
form .bidang:nth-of-type(2).anim{animation-delay:.36s}
form .bidang:nth-of-type(3).anim{animation-delay:.43s}
button.anim{animation-delay:.5s}
.catatan.anim{animation-delay:.57s}

/* ---- Panel kiri: form ---- */
.panel-form{flex:0 0 42%;display:flex;flex-direction:column;justify-content:center;
  padding:48px 64px;max-width:520px}
.logo{width:150px;margin-bottom:36px}
.logo img{display:block;width:100%;height:auto}
.jd{font-size:26px;font-weight:800;letter-spacing:-.02em;margin-bottom:6px}
.sb{font-size:13.5px;color:var(--teks-lemah);margin-bottom:32px;line-height:1.6}
label{display:block;font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--teks-lemah);margin-bottom:7px}
.bidang{margin-bottom:18px}
input{width:100%;padding:12px 14px;border:1.5px solid var(--garis);border-radius:9px;
  font-size:14px;font-family:inherit;background:var(--latar);
  transition:border-color .2s,background .2s,box-shadow .25s,transform .15s}
input:focus{border-color:var(--hijau);background:var(--putih);
  box-shadow:0 0 0 4px var(--hijau-muda);transform:translateY(-1px)}
.err{color:var(--merah);background:var(--merah-muda);border:1px solid #F3C6C2;
  border-radius:8px;padding:10px 13px;font-size:12.5px;font-weight:600;margin-bottom:18px;
  animation:masuk .35s ease forwards}
button{width:100%;padding:13px;border:none;border-radius:9px;
  background:linear-gradient(135deg,var(--hijau),var(--hijau-tua));
  background-size:160% 160%;background-position:0% 50%;
  color:#fff;font-size:14.5px;font-weight:700;font-family:inherit;cursor:pointer;
  transition:filter .2s,box-shadow .2s,transform .12s,background-position .4s;
  box-shadow:0 8px 20px rgba(10,122,92,.28);margin-top:6px}
button:hover{filter:brightness(1.06);box-shadow:0 10px 24px rgba(10,122,92,.36);background-position:100% 50%}
button:active{transform:scale(.98)}
.catatan{margin-top:28px;font-size:11.5px;color:var(--teks-lemah);line-height:1.6}

.password-wrap{position:relative}
.password-wrap input{padding-right:44px}
.password-wrap .toggle-password{position:absolute;top:0;right:0;bottom:0;width:44px;
  border:none;background:transparent;cursor:pointer;display:flex;align-items:center;
  justify-content:center;color:var(--teks-lemah);transition:color .15s}
.password-wrap .toggle-password:hover{color:var(--teks)}
.password-wrap .toggle-password svg{width:19px;height:19px}

.captcha-wrap{position:relative;width:100%;height:44px;border:1.5px solid var(--garis);
  border-radius:9px;overflow:hidden;background:var(--latar)}
/* contain, BUKAN cover — gambar captcha sumbernya 345x65 (rasio lebar
   jauh lebih pendek dari kotak tampilan), "cover" memotong ATAS/BAWAH
   karakter supaya penuhi kotak, persis penyebab huruf terpotong/susah
   dibaca yang dilaporkan pengguna. */
.captcha-wrap img{display:block;width:100%;height:100%;object-fit:contain}
.captcha-wrap .segarkan{position:absolute;top:0;right:0;bottom:0;width:44px;border:none;
  border-left:1.5px solid var(--garis);background:rgba(255,255,255,.88);cursor:pointer;
  font-size:16px;color:var(--teks-lemah);transition:background .15s,color .15s}
.captcha-wrap .segarkan:hover{background:var(--putih);color:var(--teks)}

/* ---- Panel kanan: visual gedung ---- */
.panel-visual{flex:1;position:relative;overflow:hidden;background:var(--hijau-tua);
  opacity:0;animation:kembang .8s cubic-bezier(.22,.61,.36,1) .1s forwards}
.panel-visual-img{position:absolute;inset:0;
  background:url('{{ asset('images/Gedung_Bank_NTB_Syariah_4K_HD.jpg') }}') center/cover no-repeat;
  animation:hela 6s cubic-bezier(.22,.61,.36,1) forwards}
.panel-visual::before{content:'';position:absolute;inset:0;z-index:1;
  background:linear-gradient(160deg,rgba(6,78,59,.94) 0%,rgba(6,78,59,.62) 45%,rgba(6,78,59,.30) 100%)}
.panel-visual::after{content:'';position:absolute;left:0;top:0;bottom:0;width:120px;z-index:1;
  background:linear-gradient(90deg,var(--putih) 0%,rgba(255,255,255,0) 100%)}
.visual-konten{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;
  justify-content:flex-end;padding:56px 64px;color:#fff}
.visual-konten .merek{display:flex;align-items:center;gap:16px;
  opacity:0;animation:masuk .6s cubic-bezier(.22,.61,.36,1) .5s forwards}
.visual-konten .merek .plat{background:#fff;border-radius:10px;padding:10px 12px;width:64px;flex-shrink:0}
.visual-konten .merek .plat img{display:block;width:100%;height:auto}
.visual-konten .merek .nm{font-size:34px;font-weight:800;letter-spacing:-.02em;line-height:1.2;
  max-width:460px}
.visual-konten .merek .nm small{display:block;font-weight:600;font-size:15px;opacity:.85;
  letter-spacing:0;margin-top:5px}

@media (max-width:900px){
  .split{flex-direction:column}
  .panel-form{flex:none;max-width:100%;padding:40px 24px}
  .panel-visual{min-height:260px}
  .panel-visual::after{display:none}
  .visual-konten{padding:28px}
  .visual-konten .merek .nm{font-size:22px}
  .visual-konten .merek .nm small{font-size:12px}
}
@media (prefers-reduced-motion:reduce){
  *{transition:none!important}
  .anim,.err,.panel-visual,.panel-visual-img,.visual-konten .merek{
    animation:none!important;opacity:1!important;transform:none!important}
}
</style>
</head>
<body>

<div class="split">
  <div class="panel-form">
    <div class="logo anim"><img src="{{ asset('images/logo_ntbs-B3F48E62.png') }}" alt="Bank NTB Syariah"></div>
    <div class="jd anim">Selamat Datang</div>
    <div class="sb anim">Masuk ke HCIS Bank NTB Syariah dengan NRP dan kata sandi Anda</div>

    @if ($errors->any())
      <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf

      <div class="bidang anim">
        <label for="nrp">NRP</label>
        <input type="text" id="nrp" name="nrp" value="{{ old('nrp') }}" autofocus required>
      </div>

      <div class="bidang anim">
        <label for="password">Kata Sandi</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" autocomplete="current-password" required>
          <button type="button" id="btn-toggle-password" class="toggle-password" aria-label="Tampilkan kata sandi" aria-pressed="false">
            <svg id="ikon-mata-buka" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="ikon-mata-tutup" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <div class="bidang anim">
        <label for="captcha_answer">Kode Keamanan</label>
        <div class="captcha-wrap">
          <img id="captcha-img" src="{{ captcha_src('flat') }}" alt="Kode keamanan">
          <button type="button" id="btn-refresh-captcha" class="segarkan" title="Ganti kode">⟳</button>
        </div>
        <input type="text" id="captcha_answer" name="captcha_answer" placeholder="Masukkan kode di atas" autocomplete="off" required style="margin-top:8px">
      </div>

      <button type="submit" class="anim">Masuk</button>
    </form>

    <div class="catatan anim"></div>
  </div>

  <div class="panel-visual">
    <div class="panel-visual-img"></div>
    <div class="visual-konten">
      <div class="merek">
        <div class="plat"><img src="{{ asset('images/logo_ntbs-B3F48E62.png') }}" alt="Bank NTB Syariah"></div>
        <div class="nm">Bank NTB Syariah<small>Human Capital Information System</small></div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-refresh-captcha').addEventListener('click', function () {
  fetch('{{ route('captcha.refresh') }}')
    .then(function (res) { return res.json(); })
    .then(function (data) { document.getElementById('captcha-img').src = data.captcha; });
});

document.getElementById('btn-toggle-password').addEventListener('click', function () {
  var input = document.getElementById('password');
  var terlihat = input.type === 'text';

  input.type = terlihat ? 'password' : 'text';
  document.getElementById('ikon-mata-buka').style.display = terlihat ? '' : 'none';
  document.getElementById('ikon-mata-tutup').style.display = terlihat ? 'none' : '';
  this.setAttribute('aria-label', terlihat ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
  this.setAttribute('aria-pressed', String(!terlihat));
});
</script>

</body>
</html>

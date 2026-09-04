<!DOCTYPE html>
<html lang="id" data-tema="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi 2FA — HCIS Bank NTB Syariah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B; --hijau-muda:#E6F2ED;
  --emas:#C9A227; --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5;
  --teks:#0F1F1A; --teks-lemah:#5C706A;
  --merah:#B42318; --merah-muda:#FEF3F2; --r:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--latar);
  color:var(--teks);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
:focus-visible{outline:2px solid var(--emas);outline-offset:2px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:14px;
  padding:36px 32px;max-width:400px;width:100%;box-shadow:0 8px 28px rgba(6,78,59,.08)}
.logo{width:110px;margin:0 auto 20px}
.logo img{display:block;width:100%;height:auto}
h1{font-size:19px;font-weight:800;letter-spacing:-.02em;text-align:center;margin-bottom:6px}
p.sb{font-size:12.5px;color:var(--teks-lemah);text-align:center;margin-bottom:24px;line-height:1.6}
label{display:block;font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--teks-lemah);margin-bottom:7px}
input{width:100%;padding:12px 14px;border:1.5px solid var(--garis);border-radius:9px;
  font-size:18px;font-family:'JetBrains Mono',monospace;letter-spacing:.15em;text-align:center;
  background:var(--latar)}
input:focus{border-color:var(--hijau);background:var(--putih)}
.err{color:var(--merah);background:var(--merah-muda);border:1px solid #F3C6C2;
  border-radius:8px;padding:10px 13px;font-size:12.5px;font-weight:600;margin-bottom:18px}
button{width:100%;padding:13px;border:none;border-radius:9px;background:var(--hijau);
  color:#fff;font-size:14.5px;font-weight:700;font-family:inherit;cursor:pointer;margin-top:18px}
button:hover{background:var(--hijau-tua)}
.bantuan{margin-top:18px;font-size:11.5px;color:var(--teks-lemah);text-align:center;line-height:1.6}
</style>
</head>
<body>
<div class="kartu">
  <div class="logo"><img src="{{ asset('images/logo_ntbs-B3F48E62.png') }}" alt="Bank NTB Syariah"></div>
  <h1>Verifikasi Dua Langkah</h1>
  <p class="sb">Masukkan kode 6 digit dari aplikasi authenticator Anda.</p>

  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('two-factor.challenge.verify') }}">
    @csrf
    <label for="code">Kode Otentikasi</label>
    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" autofocus required>
    <button type="submit">Verifikasi</button>
  </form>

  <p class="bantuan">Kehilangan akses ke aplikasi authenticator? Masukkan salah satu kode pemulihan Anda pada kolom di atas.</p>
</div>
</body>
</html>

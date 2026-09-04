<!DOCTYPE html>
<html lang="id" data-tema="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kode Pemulihan 2FA — HCIS Bank NTB Syariah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{
  --hijau:#0A7A5C; --hijau-tua:#064E3B; --merah:#B42318; --merah-muda:#FEF3F2;
  --putih:#fff; --latar:#F5F7F6; --garis:#E2E8E5; --teks:#0F1F1A; --teks-lemah:#5C706A; --r:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--latar);
  color:var(--teks);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.kartu{background:var(--putih);border:1px solid var(--garis);border-radius:14px;
  padding:36px 32px;max-width:440px;width:100%;box-shadow:0 8px 28px rgba(6,78,59,.08)}
.logo{width:110px;margin:0 auto 20px}
.logo img{display:block;width:100%;height:auto}
h1{font-size:19px;font-weight:800;letter-spacing:-.02em;text-align:center;margin-bottom:6px}
p.sb{font-size:12.5px;color:var(--teks-lemah);text-align:center;margin-bottom:20px;line-height:1.6}
.peringatan{margin-bottom:18px;padding:11px 13px;background:var(--merah-muda);
  border:1px solid #F3C6C2;border-radius:8px;font-size:12px;color:var(--merah);
  font-weight:600;line-height:1.6}
.kode-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:22px;
  background:var(--latar);border:1px solid var(--garis);border-radius:10px;padding:16px}
.kode-grid span{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;
  letter-spacing:.04em;text-align:center;padding:6px}
a.lanjut{display:block;text-align:center;width:100%;padding:13px;border-radius:9px;
  background:var(--hijau);color:#fff;font-size:14.5px;font-weight:700;text-decoration:none}
a.lanjut:hover{background:var(--hijau-tua)}
</style>
</head>
<body>
<div class="kartu">
  <div class="logo"><img src="{{ asset('images/logo_ntbs-B3F48E62.png') }}" alt="Bank NTB Syariah"></div>
  <h1>Simpan Kode Pemulihan Anda</h1>
  <p class="sb">2FA berhasil diaktifkan. Kode di bawah ini HANYA ditampilkan sekali — simpan di tempat aman. Gunakan salah satunya untuk masuk bila kehilangan akses ke aplikasi authenticator.</p>

  <div class="peringatan">Halaman ini tidak akan muncul lagi. Simpan sekarang sebelum melanjutkan.</div>

  <div class="kode-grid">
    @foreach ($recoveryCodes as $code)
      <span>{{ $code }}</span>
    @endforeach
  </div>

  <a href="{{ route($landingRoute) }}" class="lanjut">Sudah Disimpan, Lanjutkan</a>
</div>
</body>
</html>
